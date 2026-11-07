<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ArrayZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\AutomaticZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ProgressionZone;

/**
 * Производный уровень способности по сумме стоимостей с признаком опыта.
 */
final class CharacterDerivedLevels
{
    /**
     * Код → уровень. Нулевой уровень не пишется.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<string, int> Уровни.
     */
    public function levels(CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        $levels = [];
        foreach ($slice->getLiveRules() as $rule) {
            $level = $this->ruleLevel($slice, $choices, $rule);
            if ($level > 0) {
                $levels[$rule->getCode()] = $level;
            }
        }

        return $levels;
    }

    /**
     * Производный уровень одного правила.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return int Уровень.
     */
    private function ruleLevel(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterResolvedRule $rule): int
    {
        $base = CharacterAbilityBase::of($rule->isSpecBroken() ? null : $rule->getSpec());
        $derived = $base?->getDerivedLevel();
        if ($base === null || $derived === null) {
            return 0;
        }

        $experience = $this->experience($slice, $choices, $derived->getSourceKeyword());
        $level = 0;
        foreach ($derived->getThresholds() as $threshold) {
            if ($experience >= $threshold) {
                $level++;
            }
        }

        return $level;
    }

    /**
     * Сумма цен взятых способностей с признаком.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param string $keyword Код признака.
     *
     * @return int Опыт.
     */
    private function experience(CharacterRuleSlice $slice, CharacterChoices $choices, string $keyword): int
    {
        $sum = 0;
        foreach ($choices->getAbilities() as $ability) {
            $sum += $this->abilityExperience($slice, $ability, $keyword);
        }

        return $sum;
    }

    /**
     * Опыт одной взятой способности с признаком.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterAbilityChoice $ability Выбор.
     * @param string $keyword Код признака.
     *
     * @return int Опыт.
     */
    private function abilityExperience(CharacterRuleSlice $slice, CharacterAbilityChoice $ability, string $keyword): int
    {
        $level = $ability->getLevel();
        $rule = $slice->findLive($ability->getRuleCode());
        if (
            $level < 1
            || $rule === null
            || $rule->isSpecBroken()
            || !in_array($keyword, $rule->getKeywordCodes(), true)
        ) {
            return 0;
        }

        $base = CharacterAbilityBase::of($rule->getSpec());
        if ($base === null) {
            return 0;
        }

        return $this->cost($base, $level, $ability->getParameters());
    }

    /**
     * Цена первой платной зоны до взятого уровня.
     *
     * @param AbilityBase $base База.
     * @param int $level Уровень.
     * @param array<string, int> $parameters Параметры.
     *
     * @return int Цена.
     */
    private function cost(AbilityBase $base, int $level, array $parameters): int
    {
        foreach ($base->getZones() as $zone) {
            if ($zone instanceof AutomaticZone) {
                continue;
            }

            return $this->zoneCost($zone, $level, $parameters);
        }

        return 0;
    }

    /**
     * Цена зоны.
     *
     * @param AbilityZone $zone Зона.
     * @param int $level Уровень.
     * @param array<string, int> $parameters Параметры.
     *
     * @return int Цена.
     */
    private function zoneCost(AbilityZone $zone, int $level, array $parameters): int
    {
        return match (true) {
            $zone instanceof ArrayZone => $this->arrayCost($zone, $level),
            $zone instanceof ProgressionZone => $this->progressionCost($zone, $level),
            $zone instanceof ParameterZone => ($parameters[$zone->getParameterCode()] ?? 1) * $zone->getPerUnit(),
            default => 0,
        };
    }

    /**
     * Цена массива цен до уровня.
     *
     * @param ArrayZone $zone Зона.
     * @param int $level Уровень.
     *
     * @return int Цена.
     */
    private function arrayCost(ArrayZone $zone, int $level): int
    {
        $sum = 0;
        foreach (array_slice($zone->getLevelsCost(), 0, $level) as $price) {
            $sum += $price;
        }

        return $sum;
    }

    /**
     * Цена прогрессии до уровня.
     *
     * @param ProgressionZone $zone Зона.
     * @param int $level Уровень.
     *
     * @return int Цена.
     */
    private function progressionCost(ProgressionZone $zone, int $level): int
    {
        $sum = 0;
        $steps = min($level, $zone->getMaxLevel());
        for ($index = 0; $index < $steps; $index++) {
            $sum += $zone->getBaseCost() + $index * $zone->getStep();
        }

        return $sum;
    }
}
