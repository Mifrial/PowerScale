<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackCharacteristicFromSuccessRatingEffect;

/**
 * Бонус к характеристике урона от рейтинга успеха. Чистая арифметика эффектов.
 */
final class GameStrikeSuccessModifier
{
    /**
     * Суммирует `min(cap, ⌊РУ / floor_div⌋)` по эффектам, накрывающим попадание.
     *
     * @param array<int, ActionEffect> $effects Эффекты карточки.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания с единицы.
     * @param int $success Рейтинг успеха.
     *
     * @return int Сумма бонусов.
     */
    public function fromEffects(array $effects, string $profileType, int $hitNumber, int $success): int
    {
        $bonus = 0;
        foreach ($effects as $effect) {
            if (!$effect instanceof CurrentActionAttackCharacteristicFromSuccessRatingEffect) {
                continue;
            }

            if (!$effect->getScope()->appliesTo($profileType, $hitNumber)) {
                continue;
            }

            $divisor = $effect->getFloorDiv();
            $step = $divisor > 0 ? intdiv(max(0, $success), $divisor) : 0;
            $bonus += min($effect->getCap(), $step);
        }

        return $bonus;
    }
}
