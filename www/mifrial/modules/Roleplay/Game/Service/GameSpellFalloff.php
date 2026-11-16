<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDamage;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Снижение урона заклинания по дистанции в ипари.
 *
 * Одна формула на первый удар и на прыжки цепи: снижение читается из
 * карточки урона, минимум — из карточки урона.
 */
final class GameSpellFalloff
{
    /**
     * Снижает урон на размер за каждый ипари сверх бесплатных.
     *
     * @param CharacteristicNumber $amount Урон до снижения.
     * @param SpellDamage $damage Карточка урона.
     * @param int $distanceIpari Дистанция в ипари.
     *
     * @return CharacteristicNumber|null Урон или null ниже минимума карточки.
     */
    public function apply(
        CharacteristicNumber $amount,
        SpellDamage $damage,
        int $distanceIpari,
    ): ?CharacteristicNumber {
        $free = $damage->getFreeIpari();
        $perExtra = $damage->getSizePerExtraIpari();
        if ($free === null || $perExtra === null) {
            return $amount;
        }

        $extra = max(0, $distanceIpari - $free);
        $reduced = $extra === 0
            ? $amount
            : $amount->modify(-CharacteristicNumber::BASE_STEP * $extra * $perExtra);
        $min = $damage->getMin();
        if ($min instanceof DimensionalNumber && $reduced->characteristicDiffTo($min) < 0) {
            return null;
        }

        return $reduced;
    }
}
