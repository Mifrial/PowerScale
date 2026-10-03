<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Потолок зарядов от изученного улучшения.
 */
final class SpellChargeCap
{
    /**
     * Создаёт потолок.
     *
     * @param int $base База.
     * @param string $experienceKeywordCode Признак опыта.
     * @param array<int, SpellChargeCapStep> $steps Ступени.
     *
     * @return void
     */
    public function __construct(
        private readonly int $base,
        private readonly string $experienceKeywordCode,
        private readonly array $steps,
    ) {
    }

    /**
     * База.
     *
     * @return int Число.
     */
    public function getBase(): int
    {
        return $this->base;
    }

    /**
     * Признак опыта.
     *
     * @return string Код.
     */
    public function getExperienceKeywordCode(): string
    {
        return $this->experienceKeywordCode;
    }

    /**
     * Ступени.
     *
     * @return array<int, SpellChargeCapStep> Список.
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
}
