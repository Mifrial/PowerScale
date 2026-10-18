<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;

/**
 * Вилка дистанции по одной оси.
 */
final class MovementAxisDistance
{
    /**
     * Создаёт вилку.
     *
     * @param DimensionalFormula $min Нижняя граница.
     * @param DimensionalFormula $max Верхняя граница.
     * @param bool $minInclusive Нижняя включена.
     * @param bool $maxInclusive Верхняя включена.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalFormula $min,
        private readonly DimensionalFormula $max,
        private readonly bool $minInclusive,
        private readonly bool $maxInclusive,
    ) {
    }

    /**
     * Нижняя граница.
     *
     * @return DimensionalFormula Формула.
     */
    public function getMin(): DimensionalFormula
    {
        return $this->min;
    }

    /**
     * Верхняя граница.
     *
     * @return DimensionalFormula Формула.
     */
    public function getMax(): DimensionalFormula
    {
        return $this->max;
    }

    /**
     * Нижняя граница включена.
     *
     * @return bool true, если включена.
     */
    public function isMinInclusive(): bool
    {
        return $this->minInclusive;
    }

    /**
     * Верхняя граница включена.
     *
     * @return bool true, если включена.
     */
    public function isMaxInclusive(): bool
    {
        return $this->maxInclusive;
    }
}
