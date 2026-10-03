<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Размерное затухание.
 */
final class DimensionalDecay implements StateDecay
{
    /**
     * Создаёт затухание.
     *
     * @param DimensionalNumber $value Число.
     *
     * @return void
     */
    public function __construct(private readonly DimensionalNumber $value)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return 'dimensional';
    }

    /**
     * Число.
     *
     * @return DimensionalNumber Значение.
     */
    public function getValue(): DimensionalNumber
    {
        return $this->value;
    }
}
