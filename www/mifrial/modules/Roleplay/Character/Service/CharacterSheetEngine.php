<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveAssembly;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveKeys;

/**
 * Сборка листа для NPC. Строку character не пишет.
 */
final class CharacterSheetEngine implements ICharacterSheetEngines
{
    private readonly CharacterSaveKeys $keys;

    private readonly CharacterRevisionRemap $remap;

    /**
     * Собирает движок.
     *
     * @param ICharacterRuleSlices $ruleSlices Срезы мира.
     * @param CharacterSaveAssembly $assembly Тот же прогон, что save.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly CharacterSaveAssembly $assembly,
    ) {
        $this->keys = new CharacterSaveKeys();
        $this->remap = new CharacterRevisionRemap();
    }

    /**
     * Собирает лист на ревизии. Пустая раса не становится problem.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param int $spaceId Мир.
     * @param int $revision Ревизия.
     * @param array<string, mixed>|null $expectedSheet Сверка или null.
     *
     * @return array<string, mixed> kind, problems, revision, choices, sheet, name.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws CharacterNotFoundException Если нет ревизии.
     */
    public function build(array $choices, int $spaceId, int $revision, ?array $expectedSheet): array
    {
        return $this->assembled($choices, $spaceId, $revision, $expectedSheet);
    }

    /**
     * Ремапит choices по code и собирает лист целевой ревизии.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param int $spaceId Мир.
     * @param int $sourceRevision Исходная ревизия.
     * @param int $targetRevision Целевая ревизия.
     *
     * @return array<string, mixed> kind, problems, revision, choices, sheet, name.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws CharacterNotFoundException Если нет ревизии.
     */
    public function remap(array $choices, int $spaceId, int $sourceRevision, int $targetRevision): array
    {
        $source = $this->ruleSlices->get($spaceId, $sourceRevision);
        $target = $this->ruleSlices->get($spaceId, $targetRevision);
        $remapped = $this->remap->remap($choices, $source, $target);

        return $this->assembled($remapped['choices'], $spaceId, $targetRevision, null);
    }

    /**
     * Проверки ключей и сборка без шопа.
     *
     * @param array<string, mixed> $choices Документ.
     * @param int $spaceId Мир.
     * @param int $revision Ревизия.
     * @param array<string, mixed>|null $expectedSheet Сверка.
     *
     * @return array<string, mixed> kind, problems, revision, choices, sheet, name.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws CharacterNotFoundException Если нет ревизии.
     */
    private function assembled(array $choices, int $spaceId, int $revision, ?array $expectedSheet): array
    {
        $limits = $this->listOf($choices, 'limits');
        $abilities = $this->listOf($choices, 'abilities');
        $inventory = $this->listOf($choices, 'inventory');
        $purchases = $this->listOf($choices, 'characteristicPurchases');
        $customRules = $this->listOf($choices, 'customRules');
        $this->keys->assertLimits($limits, false);
        $this->keys->assertLists($abilities, $inventory, $purchases, $customRules);
        $this->keys->assertExpectedSheet($expectedSheet);
        $raceCode = $this->stringOf($choices, 'raceCode');
        $built = $this->assembly->build(
            $spaceId,
            $revision,
            $this->stringOf($choices, 'name'),
            $raceCode,
            $abilities,
            $inventory,
            $purchases,
            $customRules,
            $this->activeOf($choices),
            $limits,
            $this->moneyOf($choices),
            false,
            $expectedSheet,
            $choices,
        );
        $built['problems'] = $this->assembly->withClientCash($built['problems'], $this->clientMoney($choices));
        $problems = $this->withoutEmptyRace($built['problems'], $raceCode);

        return $this->report($revision, $problems, $built['choices'], $built['sheet'], $built['name']);
    }

    /**
     * Снимает CHARACTER_RACE только у пустой расы.
     *
     * @param array<int, CharacterProblem> $problems Отказы сборки.
     * @param string $raceCode Раса документа.
     *
     * @return array<int, CharacterProblem> Оставшиеся.
     */
    private function withoutEmptyRace(array $problems, string $raceCode): array
    {
        if ($raceCode !== '') {
            return $problems;
        }

        $kept = [];
        foreach ($problems as $problem) {
            if ($problem->getCode() === 'CHARACTER_RACE' && $problem->getPath() === 'raceCode') {
                continue;
            }

            $kept[] = $problem;
        }

        return $kept;
    }

    /**
     * Отчёт ok или conflicts.
     *
     * @param int $revision Ревизия сборки.
     * @param array<int, CharacterProblem> $problems Отказы.
     * @param array<string, mixed> $choices Документ.
     * @param array<string, mixed> $sheet Снимок.
     * @param string $name Имя.
     *
     * @return array<string, mixed> kind, problems, revision, choices, sheet, name.
     */
    private function report(int $revision, array $problems, array $choices, array $sheet, string $name): array
    {
        return [
            'kind' => $problems === [] ? 'ok' : 'conflicts',
            'problems' => CharacterSaveRejectedException::rows($problems),
            'revision' => $revision,
            'choices' => $choices,
            'sheet' => $sheet,
            'name' => $name,
        ];
    }

    /**
     * Список или пустой массив.
     *
     * @param array<string, mixed> $choices Документ.
     * @param string $key Ключ.
     *
     * @return array<mixed> Список.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function listOf(array $choices, string $key): array
    {
        if (!array_key_exists($key, $choices)) {
            return [];
        }

        $value = $choices[$key];
        if (!is_array($value)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $key);
        }

        return $value;
    }

    /**
     * Строка choices или пустая.
     *
     * @param array<string, mixed> $choices Документ.
     * @param string $key Ключ.
     *
     * @return string Текст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function stringOf(array $choices, string $key): string
    {
        if (!array_key_exists($key, $choices)) {
            return '';
        }

        $value = $choices[$key];
        if (!is_string($value)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $key);
        }

        return $value;
    }

    /**
     * active: нет ключа — true.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return bool|null Флаг.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function activeOf(array $choices): ?bool
    {
        if (!array_key_exists('active', $choices)) {
            return true;
        }

        if (!is_bool($choices['active'])) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: active');
        }

        return $choices['active'];
    }

    /**
     * Наличные документа или 0.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return int Остаток.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function moneyOf(array $choices): int
    {
        if (!array_key_exists('money', $choices)) {
            return 0;
        }

        if (!is_int($choices['money'])) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: money');
        }

        return $choices['money'];
    }

    /**
     * Ключ money для той же проверки, что update персонажа.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return OptionalInt Наличные или отсутствие ключа.
     */
    private function clientMoney(array $choices): OptionalInt
    {
        if (!array_key_exists('money', $choices)) {
            return OptionalInt::absent();
        }

        $money = $choices['money'];

        return OptionalInt::present(is_int($money) ? $money : null);
    }
}
