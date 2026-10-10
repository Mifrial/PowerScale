<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Размерное число шкалы характеристик. База 3–5, шаг размера 3.
 */
final class CharacteristicNumber extends DimensionalNumber
{
    public const BASE_MIN = 3;

    public const BASE_MAX = 5;

    /**
     * Создаёт число.
     *
     * @param int $base База.
     * @param int $size Размер.
     *
     * @return void
     */
    public function __construct(
        int $base,
        int $size,
    ) {
        parent::__construct($base, $size, self::BASE_MIN, self::BASE_MAX);
    }

    protected function copyWithBaseSize(int $base, int $size): static
    {
        return new static($base, $size);
    }
}
