<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Нижняя и верхняя границы дистанции профиля.
 */
final class DistanceRange
{
    /**
     * Создаёт диапазон.
     *
     * @param DistanceValue $min Нижняя граница.
     * @param DistanceValue|null $max Верхняя граница или null.
     *
     * @return void
     */
    public function __construct(
        private readonly DistanceValue $min,
        private readonly ?DistanceValue $max,
    ) {
    }

    /**
     * Нижняя граница.
     *
     * @return DistanceValue Значение.
     */
    public function getMin(): DistanceValue
    {
        return $this->min;
    }

    /**
     * Верхняя граница.
     *
     * @return DistanceValue|null Значение или null.
     */
    public function getMax(): ?DistanceValue
    {
        return $this->max;
    }
}
