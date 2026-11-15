<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellSourcePower;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Бонусы к мощи источника из формы `spell_source_power`.
 *
 * Один расчёт на лист и на каст: лист складывает вклад способностей в
 * эффективную мощь источника, каст читает уже посчитанное значение.
 */
final class CharacterSpellSourceBonus
{
    /**
     * Создаёт расчёт бонусов.
     *
     * @param FormulaEvaluations $evaluations Вычислитель формул.
     *
     * @return void
     */
    public function __construct(private readonly FormulaEvaluations $evaluations)
    {
    }

    /**
     * Вклад каждой подходящей способности.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $model Выборы.
     * @param array<string, mixed> $sheet Пересчитанный лист.
     * @param bool $pathScoped Бонусы с `path_scoped` разрешены (каст через путь).
     * @param array<string, mixed> $source Нормализованный источник.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return array<string, int> Код способности → бонус в пунктах шкалы.
     */
    public function contributions(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $sheet,
        bool $pathScoped,
        array $source,
        array $choices,
    ): array {
        $levels = $this->levels($sheet);
        if ($levels === []) {
            return [];
        }

        $context = new FormulaContext(
            $this->characteristics($sheet),
            $levels,
            $this->parameters($model),
        );

        $chosen = [];
        foreach ($model->getAbilities() as $ability) {
            $chosen[$ability->getRuleCode()] ??= $ability;
        }

        $contributions = [];
        foreach ($levels as $code => $level) {
            if ($level < 1) {
                continue;
            }

            $rule = $slice->findLive($code);
            $spec = $rule?->getSpec();
            if ($rule === null || $rule->isSpecBroken() || !$spec instanceof AbilityWithBase) {
                continue;
            }

            $block = $spec->getBase()->getSpellSourcePower();
            if (
                !$block instanceof SpellSourcePower
                || !$this->matches($block, $chosen[$code] ?? null, $slice, $pathScoped, $source, $choices)
            ) {
                continue;
            }

            try {
                $amount = $this->evaluations->evaluateScalar($block->getAmount(), $context);
            } catch (RuleInvalidException) {
                continue;
            }

            if ($amount !== 0) {
                $contributions[$code] = ($contributions[$code] ?? 0) + $amount;
            }
        }

        return $contributions;
    }

    /**
     * Сумма бонусов подходящих способностей.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $model Выборы.
     * @param array<string, mixed> $sheet Пересчитанный лист.
     * @param bool $pathScoped Бонусы с `path_scoped` разрешены.
     * @param array<string, mixed> $source Источник.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return int Бонус.
     */
    public function bonus(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $sheet,
        bool $pathScoped,
        array $source,
        array $choices,
    ): int {
        return array_sum($this->contributions($slice, $model, $sheet, $pathScoped, $source, $choices));
    }

    /**
     * Блок действует на этот источник.
     *
     * @param SpellSourcePower $block Блок формы.
     * @param CharacterAbilityChoice|null $ability Выбор способности или null для производной.
     * @param CharacterRuleSlice $slice Срез.
     * @param bool $pathScoped Бонусы с `path_scoped` разрешены.
     * @param array<string, mixed> $source Источник.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return bool true, если подходит.
     */
    private function matches(
        SpellSourcePower $block,
        ?CharacterAbilityChoice $ability,
        CharacterRuleSlice $slice,
        bool $pathScoped,
        array $source,
        array $choices,
    ): bool {
        if ($block->isPathScoped() && !$pathScoped) {
            return false;
        }

        if ($block->getScopeKind() === 'any') {
            return true;
        }

        if (($source['kind'] ?? null) !== 'item') {
            return false;
        }

        $itemCode = $this->itemRuleCode($choices, (int) ($source['inventoryId'] ?? 0));
        $rule = $slice->findLive($itemCode);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof ItemSpec) {
            return false;
        }

        return match ($block->getScopeKind()) {
            'innate' => $spec->isInnate(),
            'item' => $itemCode === $block->getItemCode(),
            'source' => $ability !== null
                && ($ability->getTargets()[$block->getTargetCode() ?? ''] ?? 0) === (int) ($source['inventoryId'] ?? 0),
            default => false,
        };
    }

    /**
     * Код предмета строки инвентаря.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param int $inventoryId Id строки.
     *
     * @return string Код или пустая строка.
     */
    private function itemRuleCode(array $choices, int $inventoryId): string
    {
        foreach (is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [] as $row) {
            if (!is_array($row) || ($row['id'] ?? null) !== $inventoryId) {
                continue;
            }

            $code = $row['ruleCode'] ?? null;

            return is_string($code) ? $code : '';
        }

        return '';
    }

    /**
     * Уровни способностей из листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, int> Код → уровень.
     */
    private function levels(array $sheet): array
    {
        $levels = [];
        foreach (is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [] as $code => $level) {
            if (is_string($code) && is_int($level)) {
                $levels[$code] = $level;
            }
        }

        return $levels;
    }

    /**
     * Характеристики листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, DimensionalNumber> Код → значение.
     */
    private function characteristics(array $sheet): array
    {
        $values = [];
        foreach (is_array($sheet['characteristics'] ?? null) ? $sheet['characteristics'] : [] as $row) {
            $code = is_array($row) ? ($row['characteristicCode'] ?? null) : null;
            $value = is_array($row) ? ($row['value'] ?? null) : null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            $size = is_array($value) ? ($value['size'] ?? null) : null;
            if (is_string($code) && is_int($base) && is_int($size)) {
                $values[$code] = new CharacteristicNumber($base, $size);
            }
        }

        return $values;
    }

    /**
     * Скалярные параметры способностей.
     *
     * @param CharacterChoices $model Выборы.
     *
     * @return array<string, int> Код → значение.
     */
    private function parameters(CharacterChoices $model): array
    {
        $values = [];
        foreach ($model->getAbilities() as $ability) {
            foreach ($ability->getParameters() as $code => $value) {
                $values[$code] = $value;
            }
        }

        return $values;
    }
}
