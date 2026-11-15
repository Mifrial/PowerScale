<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

/**
 * Целое частное числового значения характеристики.
 *
 * «Уменьшить на один размер, затем привести к простому числу» — это
 * `floor(значение / 2)`; делитель задаётся спекой.
 */
final class CharacteristicFloorDivScalar implements ScalarFormula
{
    /**
     * Создаёт узел.
     *
     * @param string $characteristicCode Код характеристики.
     * @param int $divisor Делитель.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly int $divisor,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'characteristic_floor_div';
    }

    /**
     * Код характеристики.
     *
     * @return string Значение.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Делитель.
     *
     * @return int Значение.
     */
    public function getDivisor(): int
    {
        return $this->divisor;
    }
}
