<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\MagicPath;

/**
 * Цена изучения пути.
 */
final class MagicPathStudyCost
{
    /**
     * Создаёт цену.
     *
     * @param int|float $discountFraction Доля скидки.
     * @param int|null $pairBaseCost Парная база.
     *
     * @return void
     */
    public function __construct(
        private readonly int|float $discountFraction,
        private readonly ?int $pairBaseCost,
    ) {
    }

    /**
     * Доля скидки.
     *
     * @return int|float Число.
     */
    public function getDiscountFraction(): int|float
    {
        return $this->discountFraction;
    }

    /**
     * Парная база.
     *
     * @return int|null Цена или null.
     */
    public function getPairBaseCost(): ?int
    {
        return $this->pairBaseCost;
    }
}
