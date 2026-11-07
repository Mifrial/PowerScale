<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Арифметика экономики без переполнения int.
 */
final class GameEconomyMath
{
    /**
     * Сумма без переполнения int.
     *
     * @param int $left База.
     * @param int $right Дельта.
     *
     * @return int Сумма.
     *
     * @throws GameInvalidException Если не помещается.
     */
    public static function sum(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new GameInvalidException('Economy total is too large');
        }

        if ($right < 0 && $left < PHP_INT_MIN - $right) {
            throw new GameInvalidException('Economy total is too large');
        }

        return $left + $right;
    }

    /**
     * Произведение двух неотрицательных int.
     *
     * @param int $price Цена.
     * @param int $quantity Количество.
     *
     * @return int Сумма.
     *
     * @throws GameInvalidException Если не помещается в int.
     */
    public static function product(int $price, int $quantity): int
    {
        if ($quantity !== 0 && $price > intdiv(PHP_INT_MAX, $quantity)) {
            throw new GameInvalidException('Economy total is too large');
        }

        return $price * $quantity;
    }
}
