<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Исход проверки по рейтингу: точное значение или верхняя граница.
 */
final class CheckOutcome
{
    /**
     * Создаёт исход.
     *
     * @param int|null $rating Точный рейтинг.
     * @param int|null $maxRating Рейтинг не выше.
     * @param string|null $stateRuleCode Состояние или null для чистого исхода.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $rating,
        private readonly ?int $maxRating,
        private readonly ?string $stateRuleCode,
    ) {
    }

    /**
     * Точный рейтинг.
     *
     * @return int|null Значение.
     */
    public function getRating(): ?int
    {
        return $this->rating;
    }

    /**
     * Верхняя граница рейтинга.
     *
     * @return int|null Значение.
     */
    public function getMaxRating(): ?int
    {
        return $this->maxRating;
    }

    /**
     * Код состояния исхода.
     *
     * @return string|null Код или null.
     */
    public function getStateRuleCode(): ?string
    {
        return $this->stateRuleCode;
    }

    /**
     * Подходит ли рейтинг.
     *
     * @param int $rating Рейтинг проверки.
     *
     * @return bool true если исход выбран.
     */
    public function matches(int $rating): bool
    {
        if ($this->rating !== null) {
            return $rating === $this->rating;
        }

        return $this->maxRating !== null && $rating <= $this->maxRating;
    }
}
