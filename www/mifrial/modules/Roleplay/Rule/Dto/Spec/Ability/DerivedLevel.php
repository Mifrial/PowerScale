<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Уровень способности по сумме стоимостей.
 */
final class DerivedLevel
{
    /**
     * Создаёт уровень.
     *
     * @param string $sourceKeyword Признак опыта.
     * @param array<int, int> $thresholds Пороги.
     *
     * @return void
     */
    public function __construct(
        private readonly string $sourceKeyword,
        private readonly array $thresholds,
    ) {
    }

    /**
     * Признак опыта.
     *
     * @return string Код.
     */
    public function getSourceKeyword(): string
    {
        return $this->sourceKeyword;
    }

    /**
     * Пороги.
     *
     * @return array<int, int> Список.
     */
    public function getThresholds(): array
    {
        return $this->thresholds;
    }
}
