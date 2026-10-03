<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Ступень возраста.
 */
final class AgeStep
{
    /**
     * Создаёт ступень.
     *
     * @param string $name Имя.
     * @param int $ol Очки жизни.
     * @param int $featureLimit Лимит особенностей.
     * @param array<int, AgeEffect> $effects Эффекты.
     *
     * @return void
     */
    public function __construct(
        private readonly string $name,
        private readonly int $ol,
        private readonly int $featureLimit,
        private readonly array $effects,
    ) {
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Очки жизни.
     *
     * @return int Число.
     */
    public function getOl(): int
    {
        return $this->ol;
    }

    /**
     * Лимит особенностей.
     *
     * @return int Число.
     */
    public function getFeatureLimit(): int
    {
        return $this->featureLimit;
    }

    /**
     * Эффекты.
     *
     * @return array<int, AgeEffect> Список.
     */
    public function getEffects(): array
    {
        return $this->effects;
    }
}
