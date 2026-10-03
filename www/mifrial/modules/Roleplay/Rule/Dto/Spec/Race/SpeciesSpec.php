<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Race;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;

/**
 * Spec вида: предок, способности и таблица лет.
 */
final class SpeciesSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param string|null $parentRaceCode Предок.
     * @param array<int, RaceAbilityRef> $abilities Способности.
     * @param array<int, AgeRange> $ageYears Таблица лет.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $parentRaceCode,
        private readonly array $abilities,
        private readonly array $ageYears,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'species';
    }

    /**
     * Код предка.
     *
     * @return string|null Код или null.
     */
    public function getParentRaceCode(): ?string
    {
        return $this->parentRaceCode;
    }

    /**
     * Ссылки на способности.
     *
     * @return array<int, RaceAbilityRef> Список.
     */
    public function getAbilities(): array
    {
        return $this->abilities;
    }

    /**
     * Таблица лет.
     *
     * @return array<int, AgeRange> Диапазоны.
     */
    public function getAgeYears(): array
    {
        return $this->ageYears;
    }
}
