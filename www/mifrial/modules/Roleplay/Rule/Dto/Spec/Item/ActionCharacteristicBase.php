<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;

/**
 * База силы удара, броска или выстрела.
 */
final class ActionCharacteristicBase
{
    /**
     * Создаёт базу.
     *
     * @param string $characteristic Код характеристики.
     * @param DimensionalFormula $value Формула.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristic,
        private readonly DimensionalFormula $value,
    ) {
    }

    /**
     * Характеристика.
     *
     * @return string Код.
     */
    public function getCharacteristic(): string
    {
        return $this->characteristic;
    }

    /**
     * Формула.
     *
     * @return DimensionalFormula Узел.
     */
    public function getValue(): DimensionalFormula
    {
        return $this->value;
    }
}
