<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Размерное число: целая база и целый размер.
 */
final class DimensionalNumber
{
    /**
     * Создаёт число.
     *
     * @param int $base База.
     * @param int $size Размер.
     *
     * @return void
     */
    public function __construct(
        private readonly int $base,
        private readonly int $size,
    ) {
    }

    /**
     * База.
     *
     * @return int Целое.
     */
    public function getBase(): int
    {
        return $this->base;
    }

    /**
     * Размер.
     *
     * @return int Целое.
     */
    public function getSize(): int
    {
        return $this->size;
    }
}
