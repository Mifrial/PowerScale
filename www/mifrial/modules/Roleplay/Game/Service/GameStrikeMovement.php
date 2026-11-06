<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Размерный шаг расы и её способностей.
 *
 * Шаг — характеристика персонажа, а не боевого правила; пока Character не
 * отдаёт его в листе, производная живёт здесь.
 */
final class GameStrikeMovement
{
    /**
     * Шаг из выборов расы и листа.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return DimensionalNumber Шаг.
     */
    public function step(CharacterRuleSlice $slice, array $sheet, array $choices): DimensionalNumber
    {
        $raceCode = is_string($choices['raceCode'] ?? null)
            ? $choices['raceCode']
            : (is_string($choices['race'] ?? null) ? $choices['race'] : '');

        return $this->stepOf($slice, $sheet, $raceCode);
    }

    /**
     * Шаг по коду расы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $raceCode Раса.
     *
     * @return DimensionalNumber Шаг.
     */
    public function stepOf(CharacterRuleSlice $slice, array $sheet, string $raceCode): DimensionalNumber
    {
        $step = $this->raceStep($slice, $raceCode);
        $delta = $this->racialStepDelta($slice, $sheet);

        return $delta === 0 ? $step : $step->withSize($step->getSize() + $delta);
    }

    /**
     * Шаг расы по умолчанию — один ипари.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $raceCode Раса.
     *
     * @return DimensionalNumber Шаг.
     */
    private function raceStep(CharacterRuleSlice $slice, string $raceCode): DimensionalNumber
    {
        $race = $raceCode === '' ? null : $slice->findLive($raceCode);
        $spec = $race?->getSpec();

        return $spec instanceof RaceSpec ? $spec->getMovementStep() : new DimensionalNumber(1, 0);
    }

    /**
     * Суммарный сдвиг шага от расовых способностей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return int Сдвиг размера.
     */
    private function racialStepDelta(CharacterRuleSlice $slice, array $sheet): int
    {
        $delta = 0;
        foreach ($this->abilityCodes($sheet) as $code) {
            $delta += $this->abilityStepDelta($slice, $code);
        }

        return $delta;
    }

    /**
     * Коды расовых способностей из листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<string> Коды.
     */
    private function abilityCodes(array $sheet): array
    {
        $codes = $sheet['racialAbilityCodes'] ?? [];
        if (!is_array($codes)) {
            return [];
        }

        $result = [];
        foreach ($codes as $code) {
            if (is_string($code)) {
                $result[] = $code;
            }
        }

        return $result;
    }

    /**
     * Сдвиг шага одной расовой способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код способности.
     *
     * @return int Сдвиг размера.
     */
    private function abilityStepDelta(CharacterRuleSlice $slice, string $code): int
    {
        $ability = $slice->findLive($code)?->getSpec();
        if (
            $ability instanceof AbilityActionSpec
            || $ability instanceof AbilityPlainSpec
            || $ability instanceof AbilityProcessSpec
            || $ability instanceof AbilitySpellSpec
        ) {
            return $ability->getBase()->getMovementStepSizeDelta() ?? 0;
        }

        return 0;
    }
}
