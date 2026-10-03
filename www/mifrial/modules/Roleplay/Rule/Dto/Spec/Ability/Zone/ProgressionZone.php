<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;

/**
 * Цена прогрессией.
 */
final class ProgressionZone implements AbilityZone
{
    /**
     * Создаёт цену.
     *
     * @param int $maxLevel Потолок.
     * @param int $baseCost База.
     * @param int $step Шаг.
     *
     * @return void
     */
    public function __construct(
        private readonly int $maxLevel,
        private readonly int $baseCost,
        private readonly int $step,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'progression';
    }

    /**
     * Потолок.
     *
     * @return int Уровень.
     */
    public function getMaxLevel(): int
    {
        return $this->maxLevel;
    }

    /**
     * База.
     *
     * @return int Цена.
     */
    public function getBaseCost(): int
    {
        return $this->baseCost;
    }

    /**
     * Шаг.
     *
     * @return int Цена.
     */
    public function getStep(): int
    {
        return $this->step;
    }
}
