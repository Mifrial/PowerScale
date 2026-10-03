<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Закупленная характеристика: цена и значение со ступени лестницы расы.
 */
final class CharacterPurchasedCharacteristic
{
    /**
     * Создаёт снимок ступени.
     *
     * @param string $characteristicCode Код характеристики.
     * @param int $cost Цена ступени в ОС из правила.
     * @param CharacteristicNumber $value Значение ступени из правила.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly int $cost,
        private readonly CharacteristicNumber $value,
    ) {
    }

    /**
     * Код характеристики.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Цена в ОС.
     *
     * @return int Очки из лестницы.
     */
    public function getCost(): int
    {
        return $this->cost;
    }

    /**
     * Значение характеристики.
     *
     * @return CharacteristicNumber Число ступени.
     */
    public function getValue(): CharacteristicNumber
    {
        return $this->value;
    }
}
