<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Сдвиг размера вложенной размерной формулы.
 */
final class ChangeSizeNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param DimensionalFormula $expression Вложенная формула.
     * @param int $sizeDelta Сдвиг размера.
     *
     * @return void
     */
    public function __construct(
        private readonly DimensionalFormula $expression,
        private readonly int $sizeDelta,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'change_size';
    }

    /**
     * Вложенная формула.
     *
     * @return DimensionalFormula Значение.
     */
    public function getExpression(): DimensionalFormula
    {
        return $this->expression;
    }

    /**
     * Сдвиг размера.
     *
     * @return int Значение.
     */
    public function getSizeDelta(): int
    {
        return $this->sizeDelta;
    }
}
