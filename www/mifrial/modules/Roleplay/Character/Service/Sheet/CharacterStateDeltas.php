<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterCharacteristicFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CharacteristicModifyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Дельты характеристик от эффектов состояний.
 */
final class CharacterStateDeltas
{
    /**
     * Эффекты состояний characteristic_modify.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Sheet.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Дельты.
     */
    public function compute(CharacterRuleSlice $slice, array $sheet): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return [];
        }

        $raw = [];
        foreach ($states as $row) {
            if (is_array($row)) {
                $raw = $this->mergeState($raw, $slice, $row);
            }
        }

        return $raw;
    }

    /**
     * Дельты одного состояния.
     *
     * @param array<string, array<int, array{source: string, delta: int}>> $raw Сырые дельты.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка состояния.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Сырые дельты.
     */
    private function mergeState(array $raw, CharacterRuleSlice $slice, array $row): array
    {
        $code = $row['stateRuleCode'] ?? null;
        if (!is_string($code)) {
            return $raw;
        }

        $spec = $slice->findLive($code)?->getSpec();
        if (!$spec instanceof StateSpec) {
            return $raw;
        }

        $units = $this->units($row);
        foreach ($spec->getEffects() as $effect) {
            if ($effect instanceof CharacteristicModifyEffect) {
                $raw = $this->applyEffect($raw, $slice, $effect, $code, $units);
            }
        }

        return $raw;
    }

    /**
     * Раскладывает один эффект по кодам характеристики.
     *
     * @param array<string, array<int, array{source: string, delta: int}>> $raw Сырые дельты.
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacteristicModifyEffect $effect Эффект.
     * @param string $code Код состояния.
     * @param int $units Единицы.
     *
     * @return array<string, array<int, array{source: string, delta: int}>> Сырые дельты.
     */
    private function applyEffect(
        array $raw,
        CharacterRuleSlice $slice,
        CharacteristicModifyEffect $effect,
        string $code,
        int $units,
    ): array {
        $delta = $effect->getAmount() * ($effect->isPerUnit() ? $units : 1);
        foreach (CharacterCharacteristicFormula::targetCodes($slice, $effect->getCharacteristicCode()) as $target) {
            $raw[$target][] = ['source' => $code, 'delta' => $delta];
        }

        return $raw;
    }

    /**
     * Единицы значения состояния.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int Единицы.
     */
    private function units(array $row): int
    {
        $value = $row['value'] ?? 1;
        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && is_int($value['base'] ?? null)) {
            return $value['base'];
        }

        return 1;
    }
}
