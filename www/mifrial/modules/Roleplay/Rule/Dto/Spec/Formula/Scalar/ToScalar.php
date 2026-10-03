<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;

/**
 * База размерного значения.
 */
final class ToScalar implements ScalarFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $value Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalFormula $value,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'to_scalar';
    }

    /**
     * Формула.
     *
     * @return DimensionalFormula Значение.
     */
    public function getValue(): DimensionalFormula
    {
        return $this->value;
    }
}
