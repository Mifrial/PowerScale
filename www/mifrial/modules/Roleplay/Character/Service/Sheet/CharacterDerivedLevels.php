<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;

/**
 * Производный уровень способности по сумме стоимостей с признаком опыта.
 */
final class CharacterDerivedLevels
{
    private readonly CharacterAbilityCost $cost;

    /**
     * Собирает расчёт цены.
     *
     * @return void
     */
    public function __construct()
    {
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

        return $this->cost->toLevel($base, $level, $ability->getParameters()) ?? 0;
    }
}
