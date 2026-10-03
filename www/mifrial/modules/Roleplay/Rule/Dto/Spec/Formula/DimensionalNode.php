<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Узел пары база и размер.
 */
final class DimensionalNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $number Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalNumber $number,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'dimensional';
    }

    /**
     * Пара.
     *
     * @return DimensionalNumber Значение.
     */
    public function getNumber(): DimensionalNumber
    {
        return $this->number;
    }
}
