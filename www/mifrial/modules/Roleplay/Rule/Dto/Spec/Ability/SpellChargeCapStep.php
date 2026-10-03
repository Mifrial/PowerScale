<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Ступень потолка зарядов.
 */
final class SpellChargeCapStep
{
    /**
     * Создаёт ступень.
     *
     * @param int $minExperience Минимум опыта.
     * @param int|null $cap Потолок.
     * @param int|null $perExperience На единицу опыта.
     *
     * @return void
     */
    public function __construct(
        private readonly int $minExperience,
        private readonly ?int $cap,
        private readonly ?int $perExperience,
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
     * Потолок.
     *
     * @return int|null Число или null.
     */
    public function getCap(): ?int
    {
        return $this->cap;
    }

    /**
     * На единицу опыта.
     *
     * @return int|null Число или null.
     */
    public function getPerExperience(): ?int
    {
        return $this->perExperience;
    }
}
