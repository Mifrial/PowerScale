<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Минимум текущей скорости.
 */
final class CurrentSpeedRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $axis horizontal или vertical.
     * @param string $direction Направление.
     * @param int $minStepsPerActionPoint Шагов на ОД.
     *
     * @return void
     */
    public function __construct(
        private readonly string $axis,
        private readonly string $direction,
        private readonly int $minStepsPerActionPoint,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'current_speed';
    }

    /**
     * Ось.
     *
     * @return string Код.
     */
    public function getAxis(): string
    {
        return $this->axis;
    }

    /**
     * Направление.
     *
     * @return string Код.
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * Минимум шагов на ОД.
     *
     * @return int Число.
     */
    public function getMinStepsPerActionPoint(): int
    {
        return $this->minStepsPerActionPoint;
    }
}
