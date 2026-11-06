<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;

/**
 * Коды характеристик, помеченных в live-спеке.
 *
 * Один и тот же поиск нужен делению урона, последствиям и листу участника:
 * стойкость должна быть ровно одна, иначе правило мира неоднозначно.
 */
final class GameStrikeCharacteristicCodes
{
    /**
     * Код единственной живой характеристики стойкости.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     */
    public static function enduranceCode(CharacterRuleSlice $slice): string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && $spec->isDamageEndurance()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $matched[0];
    }
}
