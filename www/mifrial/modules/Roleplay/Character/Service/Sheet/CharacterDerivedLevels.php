<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityAggregate;

/**
 * Производный уровень способности по сумме стоимостей с признаком опыта.
 */
final class CharacterDerivedLevels
{
    private readonly CharacterKeywordExperience $experience;

    private readonly CharacterAbilityCost $cost;

    /**
     * Собирает расчёт опыта.
     *
     * @return void
     */
    public function __construct()
    {
        $this->experience = new CharacterKeywordExperience();
        $this->cost = new CharacterAbilityCost();
    }

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
            $level = max(
                $this->ruleLevel($slice, $choices, $rule),
                $this->aggregateLevel($slice, $choices, $rule),
            );
            if ($level > 0) {
                $levels[$rule->getCode()] = $level;
            }
        }

        return $levels;
    }

    /**
     * Уровень агрегата «Развитие X»: для ступени N нужно N методов с ценой не ниже N.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return int Уровень.
     */
    private function aggregateLevel(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterResolvedRule $rule): int
    {
        $base = CharacterAbilityBase::of($rule->isSpecBroken() ? null : $rule->getSpec());
        $aggregate = $base?->getAggregate();
        if ($aggregate === null) {
            return 0;
        }

        $costs = [];
        foreach ($choices->getAbilities() as $ability) {
            $cost = $this->methodCost($slice, $ability, $aggregate);
            if ($cost !== null) {
                $costs[] = $cost;
            }
        }

        sort($costs);

        $level = 0;
        foreach ($aggregate->getLevels() as $index => $need) {
            $tier = $index + 1;
            if ($need <= 0) {
                $level = $tier;
                continue;
            }

            $taken = [];
            foreach ($costs as $position => $cost) {
                if (count($taken) >= $need) {
                    break;
                }

                if ($cost >= $tier) {
                    $taken[] = $position;
                }
            }

            if (count($taken) < $need) {
                break;
            }

            foreach (array_reverse($taken) as $position) {
                array_splice($costs, $position, 1);
            }

            $level = $tier;
        }

        return $level;
    }

    /**
     * Цена способности, если она метод агрегата.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterAbilityChoice $ability Выбор.
     * @param AbilityAggregate $aggregate Агрегат.
     *
     * @return int|null Цена или null.
     */
    private function methodCost(
        CharacterRuleSlice $slice,
        CharacterAbilityChoice $ability,
        AbilityAggregate $aggregate,
    ): ?int {
        if ($ability->getLevel() < 1) {
            return null;
        }

        $rule = $slice->findLive($ability->getRuleCode());
        if (
            $rule === null
            || $rule->isSpecBroken()
            || !in_array($aggregate->getMethodKeyword(), $rule->getKeywordCodes(), true)
        ) {
            return null;
        }

        $base = CharacterAbilityBase::of($rule->getSpec());
        if ($base === null || $base->getAggregate() !== null) {
            return null;
        }

        $cost = $this->cost->toLevel($base, $ability->getLevel(), $ability->getParameters());

        return $cost !== null && $cost > 0 ? $cost : null;
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

        $experience = $this->experience->experience($slice, $choices, $derived->getSourceKeyword());
        $level = 0;
        foreach ($derived->getThresholds() as $threshold) {
            if ($experience >= $threshold) {
                $level++;
            }
        }

        return $level;
    }
}
