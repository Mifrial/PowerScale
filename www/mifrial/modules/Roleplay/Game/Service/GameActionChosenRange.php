<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;

/**
 * Диапазон выбранной суммы ресурса.
 */
final class GameActionChosenRange
{
    /**
     * Выходит ли сумма за доступный диапазон.
     *
     * @param int $amount Выбранная сумма.
     * @param ScalarResourceValue|null $current Текущее значение.
     * @param bool $allowZero Разрешить 0.
     *
     * @return bool true, если сумма недопустима.
     */
    public function isOutOfRange(int $amount, ?ScalarResourceValue $current, bool $allowZero): bool
    {
        if ($amount < 0 || $current === null || $amount > $current->getValue()) {
            return true;
        }

        return !$allowZero && $amount < 1;
    }
}
