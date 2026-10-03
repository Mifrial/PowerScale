<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Race;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Ступень закупки характеристики расы.
 */
final class RacePurchaseLevel
{
    /**
     * Создаёт ступень.
     *
     * @param int $cost Цена в ОС.
     * @param CharacteristicNumber $value Значение.
     *
     * @return void
     */
    public function __construct(
        private readonly int $cost,
        private readonly CharacteristicNumber $value,
    ) {
    }

    /**
     * Цена.
     *
     * @return int Очки.
     */
    public function getCost(): int
    {
        return $this->cost;
    }

    /**
     * Значение.
     *
     * @return CharacteristicNumber База.
     */
    public function getValue(): CharacteristicNumber
    {
        return $this->value;
    }
}
