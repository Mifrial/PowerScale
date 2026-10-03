<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Частичное перекрытие цены по признаку.
 */
final class ItemModifierPriceOverride
{
    /**
     * Создаёт перекрытие.
     *
     * @param int|float|null $factor Множитель.
     * @param int|null $addGm Слагаемое.
     * @param int|null $addGmPer100g Слагаемое на 100 г.
     * @param int|null $minFinalGm Нижний порог.
     *
     * @return void
     */
    public function __construct(
        private readonly int|float|null $factor,
        private readonly ?int $addGm,
        private readonly ?int $addGmPer100g,
        private readonly ?int $minFinalGm,
    ) {
    }

    /**
     * Множитель.
     *
     * @return int|float|null Число или null.
     */
    public function getFactor(): int|float|null
    {
        return $this->factor;
    }

    /**
     * Слагаемое.
     *
     * @return int|null Число или null.
     */
    public function getAddGm(): ?int
    {
        return $this->addGm;
    }

    /**
     * Слагаемое на 100 г.
     *
     * @return int|null Число или null.
     */
    public function getAddGmPer100g(): ?int
    {
        return $this->addGmPer100g;
    }

    /**
     * Нижний порог.
     *
     * @return int|null Число или null.
     */
    public function getMinFinalGm(): ?int
    {
        return $this->minFinalGm;
    }
}
