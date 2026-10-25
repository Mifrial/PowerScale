<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Факты для проверки требований способности. Только скаляры и размерные числа.
 */
final class RequirementValues
{
    /**
     * Создаёт факты.
     *
     * @param RequirementNumbers $numbers Уровни, характеристики и лимиты.
     * @param RequirementTraits $traits Признаки и владение оружием.
     * @param RequirementUnmodelled $unmodelled Скорость и магия.
     *
     * @return void
     */
    public function __construct(
        public readonly RequirementNumbers $numbers = new RequirementNumbers(),
        public readonly RequirementTraits $traits = new RequirementTraits(),
        public readonly RequirementUnmodelled $unmodelled = new RequirementUnmodelled(),
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
        return new self($this->numbers->withLevels($levels), $this->traits, $this->unmodelled);
    }
}
