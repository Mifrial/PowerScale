<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Профиль блока щита.
 */
final class BlockProfile
{
    /**
     * Создаёт профиль.
     *
     * @param DimensionalNumber $efficiency Эффективность.
     * @param DimensionalNumber $defense Защита.
     * @param array<int, ResistanceSlot> $resistances Сопротивления.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalNumber $efficiency,
        private readonly DimensionalNumber $defense,
        private readonly array $resistances,
    ) {
    }

    /**
     * Эффективность.
     *
     * @return DimensionalNumber Число.
     */
    public function getEfficiency(): DimensionalNumber
    {
        return $this->efficiency;
    }

    /**
     * Защита.
     *
     * @return DimensionalNumber Число.
     */
    public function getDefense(): DimensionalNumber
    {
        return $this->defense;
    }

    /**
     * Сопротивления.
     *
     * @return array<int, ResistanceSlot> Список.
     */
    public function getResistances(): array
    {
        return $this->resistances;
    }
}
