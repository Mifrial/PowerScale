<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Race;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Стартовая характеристика расы.
 */
final class RaceCharacteristic
{
    /**
     * Создаёт строку.
     *
     * @param string $characteristicCode Код.
     * @param string $mode Режим fixed или purchased.
     * @param CharacteristicNumber $base База.
     * @param array<int, RacePurchaseLevel> $purchase Лестница.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly string $mode,
        private readonly CharacteristicNumber $base,
        private readonly array $purchase,
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
     * Режим.
     *
     * @return string fixed или purchased.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * База.
     *
     * @return CharacteristicNumber Значение.
     */
    public function getBase(): CharacteristicNumber
    {
        return $this->base;
    }

    /**
     * Лестница закупки.
     *
     * @return array<int, RacePurchaseLevel> Ступени.
     */
    public function getPurchase(): array
    {
        return $this->purchase;
    }
}
