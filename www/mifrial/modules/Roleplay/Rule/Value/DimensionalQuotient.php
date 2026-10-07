<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Целое частное и остаток-пара деления размерных чисел.
 */
final class DimensionalQuotient
{
    /**
     * Создаёт результат деления.
     *
     * @param int $quotient Частное.
     * @param DimensionalNumber $remainder Остаток.
     *
     * @return void
     */
    public function __construct(
        private readonly int $quotient,
        private readonly DimensionalNumber $remainder,
    ) {
    }

    /**
     * Целое частное.
     *
     * @return int Число.
     */
    public function getQuotient(): int
    {
        return $this->quotient;
    }

    /**
     * Остаток на общем размере.
     *
     * @return DimensionalNumber Пара.
     */
    public function getRemainder(): DimensionalNumber
    {
        return $this->remainder;
    }
}
