<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;

/**
 * Эффективный уровень экземпляра способности кастера.
 */
trait GameSpellCastAbilityLevelTrait
{
    /**
     * Эффективный уровень экземпляра.
     *
     * @param array<string, mixed> $abilityLevels Уровни листа.
     * @param CharacterAbilityChoice $ability Экземпляр.
     *
     * @return int Уровень.
     */
    private function levelOf(array $abilityLevels, CharacterAbilityChoice $ability): int
    {
        $level = $abilityLevels[$ability->getRuleCode()] ?? null;

        return is_int($level) ? $level : $ability->getLevel();
    }

    /**
     * Изученная карточка способности с общими полями.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $abilityLevels Уровни листа.
     * @param CharacterAbilityChoice $ability Экземпляр.
     *
     * @return AbilityWithBase|null Форма или null.
     */
    private function learnedAbility(
        CharacterRuleSlice $slice,
        array $abilityLevels,
        CharacterAbilityChoice $ability,
    ): ?AbilityWithBase {
        $code = $ability->getRuleCode();
        if ($this->levelOf($abilityLevels, $ability) < 1) {
            return null;
        }

        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof AbilityWithBase ? $spec : null;
    }
}
