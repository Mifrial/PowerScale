<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Race;

/**
 * Диапазон лет вида: ступень и полуинтервал.
 */
final class AgeRange
{
    /**
     * Создаёт диапазон.
     *
     * @param string $age Код ступени.
     * @param int $ageStart Начало лет.
     * @param int $ageEnd Конец лет.
     *
     * @return void
     */
    public function __construct(
        private readonly string $age,
        private readonly int $ageStart,
        private readonly int $ageEnd,
    ) {
    }

    /**
     * Ступень.
     *
     * @return string Код.
     */
    public function getAge(): string
    {
        return $this->age;
    }

    /**
     * Начало лет.
     *
     * @return int Год.
     */
    public function getAgeStart(): int
    {
        return $this->ageStart;
    }

    /**
     * Конец лет.
     *
     * @return int Год.
     */
    public function getAgeEnd(): int
    {
        return $this->ageEnd;
    }
}
