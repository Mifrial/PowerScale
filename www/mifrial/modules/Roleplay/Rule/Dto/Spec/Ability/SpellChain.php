<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Цепь автопопадания после повреждений.
 */
final class SpellChain
{
    /**
     * Создаёт цепь.
     *
     * @param int $damageSizePerHop Размер урона на прыжок.
     * @param DimensionalNumber $min Минимум.
     * @param string $retarget Откуда перенацеливать.
     * @param string $sameTarget Как держать цель.
     *
     * @return void
     */
    public function __construct(
        private readonly int $damageSizePerHop,
        private readonly DimensionalNumber $min,
        private readonly string $retarget,
        private readonly string $sameTarget,
    ) {
    }

    /**
     * Размер на прыжок.
     *
     * @return int Число.
     */
    public function getDamageSizePerHop(): int
    {
        return $this->damageSizePerHop;
    }

    /**
     * Минимум.
     *
     * @return DimensionalNumber Число.
     */
    public function getMin(): DimensionalNumber
    {
        return $this->min;
    }

    /**
     * Перенацеливание.
     *
     * @return string Код.
     */
    public function getRetarget(): string
    {
        return $this->retarget;
    }

    /**
     * Та же цель.
     *
     * @return string Код.
     */
    public function getSameTarget(): string
    {
        return $this->sameTarget;
    }
}
