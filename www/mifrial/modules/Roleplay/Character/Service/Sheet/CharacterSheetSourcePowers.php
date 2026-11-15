<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicParameterGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Эффективная мощь источников листа: мощь экземпляра и `spell_source_power`.
 *
 * Результат — `sheet.sourcePowers` (по строке инвентаря) и поднятое
 * значение характеристики мощи (лучший источник). Каст читает это же
 * значение, а не считает бонус заново.
 */
final class CharacterSheetSourcePowers
{
    /**
     * Создаёт расчёт.
     *
     * @param CharacterSpellSourceBonus $bonus Вклады способностей.
     *
     * @return void
     */
    public function __construct(private readonly CharacterSpellSourceBonus $bonus)
    {
    }

    /**
     * Добавляет эффективные мощности источников в лист.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $model Выборы.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return array<string, mixed> Лист.
     */
    public function apply(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $sheet,
        array $choices,
    ): array {
        $powers = $this->powers($slice, $model, $sheet, $choices);
        if ($powers === []) {
            return $sheet;
        }

        $sheet['sourcePowers'] = $powers;
        $this->raiseCharacteristics($sheet, $powers);

        return $sheet;
    }

    /**
     * Строки эффективной мощи источников.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $model Выборы.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function powers(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $sheet,
        array $choices,
    ): array {
        $powers = [];
        foreach (is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $ruleCode = $row['ruleCode'] ?? null;
            if (!is_int($id) || $id <= 0 || !is_string($ruleCode) || $ruleCode === '') {
                continue;
            }

            $rule = $slice->findLive($ruleCode);
            $spec = $rule?->getSpec();
            if ($rule === null || $rule->isSpecBroken() || !$spec instanceof ItemSpec) {
                continue;
            }

            $equipped = ($row['equipped'] ?? false) === true;
            if (!$equipped && !$spec->isInnate()) {
                continue;
            }

            $parameters = is_array($row['parameters'] ?? null) ? $row['parameters'] : [];
            $source = ['kind' => 'item', 'inventoryId' => $id];
            $contributions = $this->bonus->contributions($slice, $model, $sheet, true, $source, $choices);
            $bonus = array_sum($contributions);

            foreach ($spec->getGrants() as $grant) {
                if (!$grant instanceof CharacteristicParameterGrant) {
                    continue;
                }

                $value = $parameters[$grant->getParameterCode()] ?? null;
                $base = is_array($value) ? ($value['base'] ?? null) : null;
                $size = is_array($value) ? ($value['size'] ?? null) : null;
                if (!is_int($base) || !is_int($size)) {
                    continue;
                }

                try {
                    $number = new CharacteristicNumber($base * $grant->getPerUnit(), $size);
                    $effective = $bonus === 0 ? $number : $number->modify($bonus);
                } catch (RuleInvalidException) {
                    continue;
                }

                $powers[] = [
                    'inventoryId' => $id,
                    'ruleCode' => $ruleCode,
                    'characteristicCode' => $grant->getCharacteristicCode(),
                    'base' => ['base' => $number->getBase(), 'size' => $number->getSize()],
                    'bonus' => $bonus,
                    'value' => ['base' => $effective->getBase(), 'size' => $effective->getSize()],
                    'contributions' => $this->contributionsView($contributions),
                ];
            }
        }

        return $powers;
    }

    /**
     * Поднимает характеристику до лучшего эффективного источника.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, array<string, mixed>> $powers Источники.
     *
     * @return void
     */
    private function raiseCharacteristics(array &$sheet, array $powers): void
    {
        $best = [];
        $bestPower = [];
        foreach ($powers as $power) {
            $code = $power['characteristicCode'] ?? null;
            $value = $power['value'] ?? null;
            if (!is_string($code) || !is_array($value)) {
                continue;
            }

            $base = $value['base'] ?? null;
            $size = $value['size'] ?? null;
            if (!is_int($base) || !is_int($size)) {
                continue;
            }

            try {
                $number = new CharacteristicNumber($base, $size);
            } catch (RuleInvalidException) {
                continue;
            }

            $current = $best[$code] ?? null;
            if ($current === null || $current->characteristicCompare($number) < 0) {
                $best[$code] = $number;
                $bestPower[$code] = $power;
            }
        }

        if (!is_array($sheet['characteristics'] ?? null)) {
            return;
        }

        foreach ($sheet['characteristics'] as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = $row['characteristicCode'] ?? null;
            if (!is_string($code) || !isset($bestPower[$code])) {
                continue;
            }

            $power = $bestPower[$code];
            $bonus = is_int($power['bonus'] ?? null) ? $power['bonus'] : 0;
            $base = $power['base'] ?? null;
            $baseValue = is_array($base) ? ($base['base'] ?? null) : null;
            $baseSize = is_array($base) ? ($base['size'] ?? null) : null;
            if (!is_int($baseValue) || !is_int($baseSize) || $bonus === 0) {
                continue;
            }

            $modifiers = is_array($row['modifiers'] ?? null) ? $row['modifiers'] : [];
            $delta = is_int($row['delta'] ?? null) ? $row['delta'] : 0;
            foreach ($power['contributions'] as $contribution) {
                $modifiers[] = $contribution;
                $delta += is_int($contribution['delta'] ?? null) ? $contribution['delta'] : 0;
            }

            $rowBase = is_array($row['base'] ?? null) ? $row['base'] : [];
            $rowValue = is_array($row['value'] ?? null) ? $row['value'] : [];
            $sameBase = ($rowBase['base'] ?? null) === $baseValue && ($rowBase['size'] ?? null) === $baseSize;
            $valueBase = $sameBase ? ($rowValue['base'] ?? null) : $baseValue;
            $valueSize = $sameBase ? ($rowValue['size'] ?? null) : $baseSize;

            try {
                $value = new CharacteristicNumber(is_int($valueBase) ? $valueBase : $baseValue, is_int($valueSize) ? $valueSize : $baseSize);
                $value = $value->modify($sameBase ? $bonus : $delta);
            } catch (RuleInvalidException) {
                continue;
            }

            $sheet['characteristics'][$index] = [
                ...$row,
                'base' => ['base' => $baseValue, 'size' => $baseSize],
                'value' => ['base' => $value->getBase(), 'size' => $value->getSize()],
                'delta' => $delta,
                'modifiers' => $modifiers,
            ];
        }
    }

    /**
     * Вклады в виде строк листа.
     *
     * @param array<string, int> $contributions Вклады.
     *
     * @return array<int, array{sourceRuleCode: string, delta: int}> Строки.
     */
    private function contributionsView(array $contributions): array
    {
        $rows = [];
        foreach ($contributions as $code => $delta) {
            $rows[] = ['sourceRuleCode' => $code, 'delta' => $delta];
        }

        return $rows;
    }
}
