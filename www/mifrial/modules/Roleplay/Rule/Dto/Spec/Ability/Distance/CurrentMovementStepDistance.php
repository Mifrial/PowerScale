<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance;

/**
 * Ветка current_movement_step.
 */
final class CurrentMovementStepDistance implements ProcessDistance
{
    /**
     * Создаёт ветку.
     *
     * @param int $multiplier Множитель.
     *
     * @return void
     */
    public function __construct(
        private readonly int $multiplier,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
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
