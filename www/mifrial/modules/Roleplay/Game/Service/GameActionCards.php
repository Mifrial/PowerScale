<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\RequirePreviousAttackEffect;

/**
 * Поиск и проверка не-атакующих карточек действия.
 */
final class GameActionCards
{
    /**
     * Код единственной карточки с нужной боевой семантикой.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $combatAction Семантика, например wait.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточки нет или их несколько.
     */
    public function getByCombatAction(CharacterRuleSlice $slice, string $combatAction): string
    {
        $codes = [];
        foreach ($slice->getLiveRules() as $rule) {
            if ($rule->isSpecBroken()) {
                continue;
            }

            $spec = $rule->getSpec();
            if ($spec instanceof AbilityActionSpec && $spec->getBase()->getCombatAction() === $combatAction) {
                $codes[] = $rule->getCode();
            }
        }

        if (count($codes) !== 1) {
            throw new GameInvalidException('Game action is missing or ambiguous');
        }

        return $codes[0];
    }

    /**
     * Проверяет, что карточка существует и не является ударом.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $actionRuleCode Карточка.
     *
     * @return AbilityActionSpec Спецификация действия.
     *
     * @throws GameInvalidException Если карточка битая или является ударом.
     */
    public function assertNonAttackAction(CharacterRuleSlice $slice, string $actionRuleCode): AbilityActionSpec
    {
        $rule = $slice->findLive($actionRuleCode);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof AbilityActionSpec) {
            throw new GameInvalidException('Game action card is invalid');
        }

        if ($spec->getBase()->getAttackMode() !== null) {
            throw new GameInvalidException('Game action card is not an action');
        }

        return $spec;
    }

    /**
     * Нужен ли карточке предыдущий удар.
     *
     * @param AbilityActionSpec $spec Спецификация.
     *
     * @return bool true, если есть require_previous_attack.
     */
    public function hasRequirePreviousAttack(AbilityActionSpec $spec): bool
    {
        foreach ($spec->getBase()->getActionEffects() as $effect) {
            if ($effect instanceof RequirePreviousAttackEffect) {
                return true;
            }
        }

        return false;
    }
}
