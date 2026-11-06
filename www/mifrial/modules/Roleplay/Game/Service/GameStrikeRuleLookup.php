<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;

/**
 * Типизированный поиск карточек боя в срезе ревизии.
 *
 * Здесь живут только «найди живое правило и проверь вид спеки», без правил
 * дистанции, урона или броска.
 */
final class GameStrikeRuleLookup
{
    /**
     * База карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return AbilityBase|null База или null.
     */
    public static function actionBase(CharacterRuleSlice $slice, string $actionRuleCode): ?AbilityBase
    {
        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        if ($spec instanceof AbilityActionSpec || $spec instanceof AbilityProcessSpec) {
            return $spec->getBase();
        }

        return null;
    }
}
