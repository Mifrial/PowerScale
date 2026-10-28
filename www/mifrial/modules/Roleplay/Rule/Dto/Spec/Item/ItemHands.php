<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Слоты рук предмета.
 */
final class ItemHands
{
    /**
     * Создаёт слоты.
     *
     * @param int $min Минимум покоя.
     * @param int $max Максимум покоя.
     *
     * @return void
     */
    public function __construct(
        private readonly int $min,
        private readonly int $max,
    ) {
    }

    /**
     * Минимум покоя.
     *
     * @return int Число.
     */
    public function getMin(): int
    {
        return $this->min;
    }

    /**
     * Максимум покоя.
     *
     * @return int Число.
     */
    public function getMax(): int
    {
        return $this->max;
    }
}
