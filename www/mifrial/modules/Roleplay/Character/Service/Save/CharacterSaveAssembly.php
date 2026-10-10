<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Save;

use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceLimits;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceGrantReader;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;

/**
 * Прогон валидатора, шопа и снимка. Записи не делает.
 */
final class CharacterSaveAssembly
{
    private readonly CharacterChoiceAssembler $choiceAssembler;

    private readonly CharacterSheetDocument $sheetDocument;

    private readonly CharacterResourceLimits $resourceLimits;

    private readonly CharacterResourceStorage $resourceStorage;

    /**
     * Собирает части прогона.
     *
     * @param ICharacterRuleSlices $ruleSlices Срез.
     * @param ICharacterSheets $sheets Валидатор.
     * @param CharacterShopBalance $shopBalance Шоп.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly ICharacterSheets $sheets,
        private readonly CharacterShopBalance $shopBalance,
    ) {
        $this->choiceAssembler = new CharacterChoiceAssembler();
        $this->sheetDocument = new CharacterSheetDocument();
        $this->resourceLimits = new CharacterResourceLimits(
            new FormulaEvaluations(),
            new CharacterResourceGrantReader(new CharacterDonorGrants()),
            new CharacterResourceArithmetic(),
        );
        $this->resourceStorage = new CharacterResourceStorage();
    }

    /**
     * Валидатор, шоп и снимок.
     *
     * @param int $spaceId Мир.
     * @param int $revision Ревизия.
     * @param string $name Имя.
     * @param string $raceCode Раса.
     * @param array<mixed> $abilities Способности.
     * @param array<mixed> $inventory Инвентарь.
     * @param array<mixed> $characteristicPurchases Закупки.
     * @param array<mixed> $customRules Свои правила.
     * @param bool|null $active Флаг.
     * @param array<mixed> $limits Лимиты.
     * @param int|null $updateMoney Наличные update или null на create.
     * @param bool $shopCreate Считать шоп.
     * @param array<mixed>|null $expectedSheet Сверка.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed>|null $previousSheet Server-owned previous sheet.
     *
     * @return array{problems: array<int, CharacterProblem>, choices: array<string, mixed>, sheet: array<string, mixed>, name: string, active: bool}
     * Снимок и отказы.
     */
    public function build(
        int $spaceId,
        int $revision,
        string $name,
        string $raceCode,
        array $abilities,
        array $inventory,
        array $characteristicPurchases,
        array $customRules,
        ?bool $active,
        array $limits,
        ?int $updateMoney,
        bool $shopCreate,
        ?array $expectedSheet,
        array $choices,
        ?array $previousSheet = null,
    ): array {
        $slice = $this->ruleSlices->get($spaceId, $revision);
        $model = $this->choiceAssembler->assemble(
            $name,
            $raceCode,
            $abilities,
            $inventory,
            $characteristicPurchases,
            $customRules,
            $active,
        );
        $validation = $this->sheets->validate($slice, $model);
        $money = $this->moneyOf($slice, $model, $limits, $updateMoney, $shopCreate);
        $sheet = $this->buildSheet($slice, $validation, $model, $money['money'], $previousSheet);
        $choices['money'] = $money['money'];

        return $this->result($validation, $limits, $money['problems'], $sheet, $expectedSheet, $choices, $name);
    }

    /**
     * Отрицательные наличные из тела — problem. Сохранённый остаток не проверяется.
     *
     * @param array<int, CharacterProblem> $problems Уже собранные отказы.
     * @param OptionalInt $money Ключ наличных.
     *
     * @return array<int, CharacterProblem> Отказы.
     */
    public function withClientCash(array $problems, OptionalInt $money): array
    {
        if (!$money->isPresent() || $money->getValue() === null || $money->getValue() >= 0) {
            return $problems;
        }

        $problems[] = new CharacterProblem('CHARACTER_MONEY', 'Money must not be negative', 'input', 'money');

        return $problems;
    }

    /**
     * Пакет сборки.
     *
     * @param CharacterValidation $validation Валидатор.
     * @param array<mixed> $limits Лимиты.
     * @param array<int, CharacterProblem> $shopProblems Шоп.
     * @param array<string, mixed> $sheet Снимок.
     * @param array<mixed>|null $expectedSheet Сверка.
     * @param array<string, mixed> $choices Документ.
     * @param string $name Имя.
     *
     * @return array{problems: array<int, CharacterProblem>, choices: array<string, mixed>, sheet: array<string, mixed>, name: string, active: bool}
     * Снимок и отказы.
     */
    private function result(
        CharacterValidation $validation,
        array $limits,
        array $shopProblems,
        array $sheet,
        ?array $expectedSheet,
        array $choices,
        string $name,
    ): array {
        $problems = array_merge(
            $validation->getProblems(),
            $this->limitProblems($limits),
            $shopProblems,
            $this->sheetDocument->findMismatch($sheet, $expectedSheet),
        );

        return [
            'problems' => $problems,
            'choices' => $choices,
            'sheet' => $sheet,
            'name' => trim($name),
            'active' => $validation->isActive(),
        ];
    }

    /**
     * Create считает шоп. Update берёт присланный или сохранённый остаток.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $model Выборы.
     * @param array<mixed> $limits Лимиты.
     * @param int|null $updateMoney Остаток update.
     * @param bool $shopCreate Считать шоп.
     *
     * @return array{money: int, problems: array<int, CharacterProblem>} Деньги.
     */
    private function moneyOf(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $limits,
        ?int $updateMoney,
        bool $shopCreate,
    ): array {
        if (!$shopCreate) {
            return ['money' => $updateMoney ?? 0, 'problems' => []];
        }

        return $this->shopBalance->getBalance(
            $slice,
            $model->getAbilities(),
            $model->getInventory(),
            $this->budget($limits),
        );
    }

    /**
     * Потолок шопа. Нет ключа и null — потолка нет.
     *
     * @param array<mixed> $limits Лимиты.
     *
     * @return int|null Бюджет.
     */
    private function budget(array $limits): ?int
    {
        $money = $limits['money'] ?? null;

        return is_int($money) ? $money : null;
    }

    /**
     * Отрицательный потолок — problem input.
     *
     * @param array<mixed> $limits Лимиты.
     *
     * @return array<int, CharacterProblem> Отказы.
     */
    private function limitProblems(array $limits): array
    {
        $problems = [];
        foreach (['os', 'or', 'money'] as $key) {
            if (array_key_exists($key, $limits) && !$this->nonNegative($limits[$key])) {
                $problems[] = new CharacterProblem(
                    'CHARACTER_LIMIT',
                    'Limit must be a non-negative integer or null',
                    'input',
                    'limits.' . $key,
                );
            }
        }

        return $problems;
    }

    /**
     * null или целое ≥ 0.
     *
     * @param mixed $value Значение.
     *
     * @return bool true, если потолок допустим.
     */
    private function nonNegative(mixed $value): bool
    {
        return $value === null || (is_int($value) && $value >= 0);
    }

    /**
     * Собирает sheet вместе с server-owned resources.
     *
     * @param CharacterRuleSlice $slice Live-срез.
     * @param CharacterValidation $validation Validation result.
     * @param CharacterChoices $model Typed choices.
     * @param int $money Current money.
     * @param array<string, mixed>|null $previousSheet Previous sheet.
     *
     * @return array<string, mixed> Sheet document.
     *
     * @throws \Mifrial\Roleplay\Character\Exception\CharacterInvalidException При битых resources.
     */
    private function buildSheet(
        CharacterRuleSlice $slice,
        CharacterValidation $validation,
        CharacterChoices $model,
        int $money,
        ?array $previousSheet,
    ): array {
        $previousRows = $this->previousRows($previousSheet, $slice);
        $resourceRows = $this->resourceLimits->buildRows($slice, $validation, $model, $previousRows);

        return $this->sheetDocument->build(
            $validation,
            $money,
            $this->resourceStorage->serializeRows($resourceRows),
        );
    }

    /**
     * Разбирает server-owned previous resource rows.
     *
     * @param array<string, mixed>|null $previousSheet Previous sheet.
     * @param CharacterRuleSlice $slice Live rules.
     *
     * @return array<int, \Mifrial\Roleplay\Character\Dto\ResourceRow> Typed rows.
     *
     * @throws \Mifrial\Roleplay\Character\Exception\CharacterInvalidException If the stored shape is invalid.
     */
    private function previousRows(?array $previousSheet, CharacterRuleSlice $slice): array
    {
        if ($previousSheet === null || !array_key_exists('resources', $previousSheet)) {
            return [];
        }

        $resources = $previousSheet['resources'];
        if (!is_array($resources)) {
            throw new \Mifrial\Roleplay\Character\Exception\CharacterInvalidException('Character resources are invalid');
        }

        return $this->resourceStorage->parseRows($resources, $slice);
    }
}
