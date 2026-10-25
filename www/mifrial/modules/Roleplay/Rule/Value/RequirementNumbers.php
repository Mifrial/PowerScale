<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Числовые факты листа для проверки требований.
 */
final class RequirementNumbers
{
    /**
     * Создаёт факты.
     *
     * @param array<string, int> $levels Уровни способностей.
     * @param array<string, DimensionalNumber> $characteristics Характеристики.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты ресурсов.
     *
     * @return void
     */
    public function __construct(
        public readonly array $levels = [],
        public readonly array $characteristics = [],
        public readonly array $resourceLimits = [],
    ) {
    }

    /**
     * Копия с другими уровнями способностей.
     *
     * @param array<string, int> $levels Уровни.
     *
     * @return self Новые факты.
     */
    public function withLevels(array $levels): self
    {
        return new self($levels, $this->characteristics, $this->resourceLimits);
    }
}
