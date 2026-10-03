<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Узел «число» размерной формулы.
 */
final class FixedNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $value Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly int $value,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'fixed';
    }

    /**
     * Число.
     *
     * @return int Значение.
     */
    public function getValue(): int
    {
        return $this->value;
    }
}
