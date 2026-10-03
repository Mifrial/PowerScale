<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

/**
 * Разрыв размеров двух характеристик.
 */
final class CharacteristicSizeGapScalar implements ScalarFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $characteristicCodeFrom Поле.
     * @param mixed $characteristicCodeTo Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCodeFrom,
        private readonly string $characteristicCodeTo,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'characteristic_size_gap';
    }

    /**
     * От.
     *
     * @return string Значение.
     */
    public function getCharacteristicCodeFrom(): string
    {
        return $this->characteristicCodeFrom;
    }

    /**
     * До.
     *
     * @return string Значение.
     */
    public function getCharacteristicCodeTo(): string
    {
        return $this->characteristicCodeTo;
    }
}
