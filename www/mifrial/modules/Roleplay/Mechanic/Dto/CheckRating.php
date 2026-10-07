<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Итог сравнения успехов и трудности: прошёл ли порог и разность.
 */
final class CheckRating
{
    /**
     * Собирает сравнение.
     *
     * @param bool $passed Левая сторона не меньше правой.
     * @param int $rating Разность приведённых баз.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $passed,
        private readonly int $rating,
    ) {
    }

    /**
     * Проверка пройдена.
     *
     * @return bool true, если успехи не меньше трудности.
     */
    public function isPassed(): bool
    {
        return $this->passed;
    }

    /**
     * Разность приведённых баз.
     *
     * @return int Целое.
     */
    public function getRating(): int
    {
        return $this->rating;
    }
}
