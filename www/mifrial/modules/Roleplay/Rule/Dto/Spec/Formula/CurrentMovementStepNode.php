<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * N собственных шагов персонажа.
 */
final class CurrentMovementStepNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param int $multiplier Множитель шага.
     *
     * @return void
     */
    public function __construct(
        private readonly int $multiplier,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'current_movement_step';
    }

    /**
     * Множитель.
     *
     * @return int Значение.
     */
    public function getMultiplier(): int
    {
        return $this->multiplier;
    }
}
