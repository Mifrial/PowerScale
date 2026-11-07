<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterCharacteristicFormula;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Живые характеристики: раса, гранты, агрегация, потолки предмета.
 */
final class CharacterLiveCharacteristics
{
    /**
     * Создаёт расчёт.
     *
     * @param IFormulaEvaluations $evaluations Формулы.
     * @param CharacterLiveBases $bases Базы характеристик.
     * @param CharacterLiveDeltas $deltas Дельты характеристик.
     * @param CharacterLiveSenses $senses Чувства.
     * @param CharacterEquippedLive $equippedLive Надетое.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $evaluations,
        private readonly CharacterLiveBases $bases,
        private readonly CharacterLiveDeltas $deltas,
        private readonly CharacterLiveSenses $senses,
        private readonly CharacterEquippedLive $equippedLive,
    ) {
    }

    /**
     * Считает характеристики одного прогона.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок C4.
     * @param array<string, mixed> $sheet Документ sheet.
     * @param array<string, int> $abilityLevels Эффективные уровни.
     *
     * @return array<int, array<string, mixed>> Строки characteristics.
     */
    public function compute(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
        array $sheet,
        array $abilityLevels,
    ): array {
        $bases = $this->bases->compute($slice, $choices, $validation, $abilityLevels);
        $senseDelta = $this->senses->bestDelta(
            $this->senses->rows($slice, $choices, $abilityLevels, $bases),
        );
        $deltas = $this->deltas->compute($slice, $choices, $sheet, $abilityLevels, $bases, $senseDelta);
        $bases = $this->baseFrom($slice, $bases, $deltas);
        $values = $this->applied($bases, $deltas);
        $values = $this->derived($slice, $values);
        $values = $this->capped($slice, $choices, $values);

        return $this->rows($bases, $values, $deltas);
    }

    /**
     * Активные чувства листа: auto_add и выданные грантом, без статуса «отсутствует».
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок.
     * @param array<string, int> $abilityLevels Уровни.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function activeSenses(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
        array $abilityLevels,
    ): array {
        return $this->senses->active(
            $slice,
            $choices,
            $abilityLevels,
            $this->bases->compute($slice, $choices, $validation, $abilityLevels),
        );
    }

    /**
     * base_from: база донора плюс выбранные источники.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param array<string, array<int, array{source: string, delta: int}>> $deltas Дельты.
     *
     * @return array<string, CharacteristicNumber> Базы.
     */
    private function baseFrom(CharacterRuleSlice $slice, array $bases, array $deltas): array
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            $from = $spec instanceof CharacteristicSpec ? $spec->getBaseFrom() : null;
            if ($from === null) {
                continue;
            }

            $donor = $bases[$from->getCharacteristicCode()] ?? null;
            if ($donor === null) {
                continue;
            }

            $allowed = array_fill_keys($from->getSourceCodes(), true);
            $delta = $this->allowedDelta($deltas[$from->getCharacteristicCode()] ?? [], $allowed);
            $bases[$rule->getCode()] = $this->shifted($donor, $delta);
        }

        return $bases;
    }

    /**
     * Сумма дельт только от разрешённых источников.
     *
     * @param array<int, array{source: string, delta: int}> $rows Дельты.
     * @param array<string, true> $allowed Разрешённые источники.
     *
     * @return int Сумма.
     */
    private function allowedDelta(array $rows, array $allowed): int
    {
        $delta = 0;
        foreach ($rows as $row) {
            if (isset($allowed[$row['source']])) {
                $delta += $row['delta'];
            }
        }

        return $delta;
    }

    /**
     * База плюс сумма дельт.
     *
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param array<string, array<int, array{source: string, delta: int}>> $deltas Дельты.
     *
     * @return array<string, CharacteristicNumber> Значения.
     */
    private function applied(array $bases, array $deltas): array
    {
        $values = [];
        foreach ($bases as $code => $base) {
            $delta = 0;
            foreach ($deltas[$code] ?? [] as $row) {
                $delta += $row['delta'];
            }

            $values[$code] = $this->shifted($base, $delta);
        }

        return $values;
    }

    /**
     * min/max группы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, CharacteristicNumber> $values Значения.
     *
     * @return array<string, CharacteristicNumber> Значения.
     */
    private function derived(CharacterRuleSlice $slice, array $values): array
    {
        $groups = $this->groups($slice);
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            $formula = $spec instanceof CharacteristicSpec ? $spec->getFormula() : null;
            $parsed = is_string($formula) ? CharacterCharacteristicFormula::parse($formula) : null;
            $picked = $parsed === null
                ? $this->pickGroup($values, $groups, $spec, $formula)
                : $this->pick($values, $parsed['codes'], $parsed['max']);
            if ($picked !== null) {
                $values[$rule->getCode()] = $picked;
            }
        }

        return $values;
    }

    /**
     * Коды характеристик по группам.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, array<int, string>> Группа → коды.
     */
    private function groups(CharacterRuleSlice $slice): array
    {
        $groups = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if (!$spec instanceof CharacteristicSpec) {
                continue;
            }

            $group = $spec->getGroup();
            if ($group !== null && $group !== '') {
                $groups[$group][] = $rule->getCode();
            }
        }

        return $groups;
    }

    /**
     * min/max группы характеристики.
     *
     * @param array<string, CharacteristicNumber> $values Значения.
     * @param array<string, array<int, string>> $groups Группы.
     * @param RuleSpec|null $spec Спека правила.
     * @param string|null $formula Формула.
     *
     * @return CharacteristicNumber|null Число.
     */
    private function pickGroup(array $values, array $groups, ?RuleSpec $spec, ?string $formula): ?CharacteristicNumber
    {
        if ($formula !== 'min' && $formula !== 'max') {
            return null;
        }

        $group = $spec instanceof CharacteristicSpec ? $spec->getGroup() : null;

        return $this->pick($values, $groups[$group ?? ''] ?? [], $formula === 'max');
    }

    /**
     * Жёсткий потолок characteristic_limits надетого.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, CharacteristicNumber> $values Значения.
     *
     * @return array<string, CharacteristicNumber> Значения.
     */
    private function capped(CharacterRuleSlice $slice, CharacterChoices $choices, array $values): array
    {
        $context = new FormulaContext($values, []);
        foreach ($this->equippedLive->equipped($slice, $choices) as $item) {
            $spec = $item['spec'];

            $limits = array_merge(
                $spec->getArmor()?->getCharacteristicLimits() ?? [],
                $spec->getShield()?->getCharacteristicLimits() ?? [],
            );
            $values = $this->applyLimits($limits, $values, $context);
        }

        return $values;
    }

    /**
     * Применяет потолки одного предмета.
     *
     * @param array<int, mixed> $limits Потолки.
     * @param array<string, CharacteristicNumber> $values Значения.
     * @param FormulaContext $context Контекст.
     *
     * @return array<string, CharacteristicNumber> Значения.
     */
    private function applyLimits(array $limits, array $values, FormulaContext $context): array
    {
        foreach ($limits as $limit) {
            try {
                $cap = $this->evaluations->evaluateDimensional($limit->getLimit(), $context);
            } catch (RuleInvalidException) {
                continue;
            }

            $code = $limit->getCharacteristicCode();
            $current = $values[$code] ?? null;
            if ($current !== null && $current->compare($cap) > 0) {
                $values[$code] = new CharacteristicNumber($cap->getBase(), $cap->getSize());
            }
        }

        return $values;
    }

    /**
     * Строки sheet.characteristics.
     *
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param array<string, CharacteristicNumber> $values Значения.
     * @param array<string, array<int, array{source: string, delta: int}>> $deltas Дельты.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function rows(array $bases, array $values, array $deltas): array
    {
        $rows = [];
        foreach ($values as $code => $value) {
            $base = $bases[$code] ?? $value;
            $delta = 0;
            $modifiers = [];
            foreach ($deltas[$code] ?? [] as $row) {
                $delta += $row['delta'];
                $modifiers[] = ['sourceRuleCode' => $row['source'], 'delta' => $row['delta']];
            }

            $rows[] = [
                'characteristicCode' => $code,
                'ruleCode' => $code,
                'base' => ['base' => $base->getBase(), 'size' => $base->getSize()],
                'value' => ['base' => $value->getBase(), 'size' => $value->getSize()],
                'delta' => $delta,
                'modifiers' => $modifiers,
            ];
        }

        return $rows;
    }

    /**
     * Сдвиг по шкале характеристики.
     *
     * @param CharacteristicNumber $number База.
     * @param int $delta Пункты.
     *
     * @return CharacteristicNumber Число.
     */
    private function shifted(CharacteristicNumber $number, int $delta): CharacteristicNumber
    {
        if ($delta === 0) {
            return $number;
        }

        try {
            $shifted = $number->modify($delta);
        } catch (RuleInvalidException) {
            return $number;
        }

        return $shifted;
    }

    /**
     * min или max списка.
     *
     * @param array<string, CharacteristicNumber> $values Значения.
     * @param array<int, string> $codes Коды группы.
     * @param bool $max true — максимум.
     *
     * @return CharacteristicNumber|null Число.
     */
    private function pick(array $values, array $codes, bool $max): ?CharacteristicNumber
    {
        $picked = null;
        foreach ($codes as $code) {
            $value = $values[$code] ?? null;
            if ($value === null) {
                continue;
            }

            if ($picked === null || $this->better($value, $picked, $max)) {
                $picked = $value;
            }
        }

        return $picked;
    }

    /**
     * Строго ли левое число выгоднее правого.
     *
     * @param CharacteristicNumber $left Левое.
     * @param CharacteristicNumber $right Правое.
     * @param bool $max true — ищем максимум.
     *
     * @return bool true, если левое выгоднее.
     */
    private function better(CharacteristicNumber $left, CharacteristicNumber $right, bool $max): bool
    {
        $cmp = $left->compare($right);

        return $max ? $cmp > 0 : $cmp < 0;
    }
}
