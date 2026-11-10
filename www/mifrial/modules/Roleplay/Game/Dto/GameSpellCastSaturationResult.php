<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Итог выбранного насыщения.
 */
final class GameSpellCastSaturationResult
{
    /**
     * Создаёт итог.
     *
     * @param GameSpellCastSaturationSource|null $source Источник или null.
     * @param int $steps Выбранные шаги.
     * @param int $ratingSpent Потраченный успех.
     * @param int $powerDelta Прирост Мощи.
     * @param int $remainingRating Остаток успеха.
     *
     * @return void
     */
    public function __construct(
        private readonly ?GameSpellCastSaturationSource $source,
        private readonly int $steps,
        private readonly int $ratingSpent,
        private readonly int $powerDelta,
        private readonly int $remainingRating,
    ) {
    }

    /**
     * Источник.
     *
     * @return GameSpellCastSaturationSource|null Источник или null.
     */
    public function getSource(): ?GameSpellCastSaturationSource
    {
        return $this->source;
    }

    /**
     * Выбранные шаги.
     *
     * @return int Число.
     */
    public function getSteps(): int
    {
        return $this->steps;
    }

    /**
     * Потраченный успех.
     *
     * @return int Число.
     */
    public function getRatingSpent(): int
    {
        return $this->ratingSpent;
    }

    /**
     * Прирост Мощи.
     *
     * @return int Число.
     */
    public function getPowerDelta(): int
    {
        return $this->powerDelta;
    }

    /**
     * Остаток успеха.
     *
     * @return int Число.
     */
    public function getRemainingRating(): int
    {
        return $this->remainingRating;
    }
}
