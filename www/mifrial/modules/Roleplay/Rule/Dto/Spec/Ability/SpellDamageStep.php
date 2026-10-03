<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Ступень урона заклинания от опыта.
 */
final class SpellDamageStep
{
    /**
     * Создаёт ступень.
     *
     * @param int $minExperience Минимум опыта.
     * @param int $modify Сдвиг.
     *
     * @return void
     */
    public function __construct(
        private readonly int $minExperience,
        private readonly int $modify,
    ) {
    }

    /**
     * Минимум опыта.
     *
     * @return int Число.
     */
    public function getMinExperience(): int
    {
        return $this->minExperience;
    }

    /**
     * Сдвиг.
     *
     * @return int Число.
     */
    public function getModify(): int
    {
        return $this->modify;
    }
}
