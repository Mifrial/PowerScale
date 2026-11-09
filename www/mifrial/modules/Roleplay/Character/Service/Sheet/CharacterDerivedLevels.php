<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;

/**
 * Производный уровень способности по сумме стоимостей с признаком опыта.
 */
final class CharacterDerivedLevels
{
    private readonly CharacterKeywordExperience $experience;

    /**
     * Собирает расчёт опыта.
     *
     * @return void
     */
    public function __construct()
    {
        $this->experience = new CharacterKeywordExperience();
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
