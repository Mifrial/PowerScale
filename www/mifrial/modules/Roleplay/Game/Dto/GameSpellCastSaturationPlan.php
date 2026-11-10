<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Доступный выбор насыщения после броска.
 */
final class GameSpellCastSaturationPlan
{
    /**
     * Создаёт выбор.
     *
     * @param GameSpellCastSaturationSource|null $source Источник или null.
     * @param int $rating Полученный успех.
     * @param int $maxSteps Наибольшее число шагов.
     *
     * @return void
     */
    public function __construct(
        private readonly ?GameSpellCastSaturationSource $source,
        private readonly int $rating,
        private readonly int $maxSteps,
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
     * Полученный успех.
     *
     * @return int Число.
     */
    public function getRating(): int
    {
        return $this->rating;
    }

    /**
     * Наибольшее число шагов.
     *
     * @return int Число.
     */
    public function getMaxSteps(): int
    {
        return $this->maxSteps;
    }

    /**
     * Выбор есть.
     *
     * @return bool true, если шаги возможны.
     */
    public function hasChoice(): bool
    {
        return $this->source !== null && $this->maxSteps > 0;
    }

    /**
     * Блок выбора для ответа и хранения.
     *
     * @return array<string, mixed>|null Блок или null.
     */
    public function toArray(): ?array
    {
        if ($this->source === null) {
            return null;
        }

        return [
            'sourceCode' => $this->source->getRuleCode(),
            'minRating' => $this->source->getMinRating(),
            'ratingPerStep' => $this->source->getRatingPerStep(),
            'powerPerStep' => $this->source->getPowerPerStep(),
            'rating' => $this->rating,
            'maxSteps' => $this->maxSteps,
        ];
    }
}
