<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Сумма размерных формул.
 */
final class AddDimensionalNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param array<int, DimensionalFormula> $parts Слагаемые.
     *
     * @return void
     */
    public function __construct(
        private readonly array $parts,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'add';
    }

    /**
     * Слагаемые.
     *
     * @return array<int, DimensionalFormula> Значение.
     */
    public function getParts(): array
    {
        return $this->parts;
    }
}
