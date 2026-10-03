<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Трата успеха сотворения на шаги мощи.
 */
final class SpellSaturation
{
    /**
     * Создаёт насыщение.
     *
     * @param int $minRating Минимум успеха.
     * @param int $ratingPerStep Успех на шаг.
     * @param int $powerPerStep Мощь на шаг.
     *
     * @return void
     */
    public function __construct(
        private readonly int $minRating,
        private readonly int $ratingPerStep,
        private readonly int $powerPerStep,
    ) {
    }

    /**
     * Минимум успеха.
     *
     * @return int Число.
     */
    public function getMinRating(): int
    {
        return $this->minRating;
    }

    /**
     * Успех на шаг.
     *
     * @return int Число.
     */
    public function getRatingPerStep(): int
    {
        return $this->ratingPerStep;
    }

    /**
     * Мощь на шаг.
     *
     * @return int Число.
     */
    public function getPowerPerStep(): int
    {
        return $this->powerPerStep;
    }
}
