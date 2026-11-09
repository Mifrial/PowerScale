<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;

/**
 * Опыт признака: сумма цен активных способностей с этим признаком.
 */
final class CharacterKeywordExperience
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
     * Сумма цен способностей с признаком.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param string $keyword Код признака.
     * @param array<string, int> $levels Эффективные уровни; нет кода — уровень экземпляра.
     *
     * @return int Опыт.
     */
    public function experience(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        string $keyword,
        array $levels = [],
    ): int {
        $sum = 0;
        foreach ($choices->getAbilities() as $ability) {
            $sum += $this->abilityExperience($slice, $ability, $keyword, $levels);
        }

        return $sum;
    }

    /**
     * Опыт одной взятой способности с признаком.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterAbilityChoice $ability Выбор.
     * @param string $keyword Код признака.
     * @param array<string, int> $levels Эффективные уровни; нет кода — уровень экземпляра.
     *
     * @return int Опыт.
     */
    private function abilityExperience(
        CharacterRuleSlice $slice,
        CharacterAbilityChoice $ability,
        string $keyword,
        array $levels,
    ): int {
        $level = $levels[$ability->getRuleCode()] ?? $ability->getLevel();
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
