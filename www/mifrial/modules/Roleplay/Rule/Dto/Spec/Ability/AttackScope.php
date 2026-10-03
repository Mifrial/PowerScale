<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Область атаки эффекта.
 */
final class AttackScope
{
    /**
     * Создаёт область.
     *
     * @param array<int, string> $components strike, throw, shoot.
     * @param int|string $hitCount Число попаданий или all.
     *
     * @return void
     */
    public function __construct(
        private readonly array $components,
        private readonly int|string $hitCount,
    ) {
    }

    /**
     * Компоненты атаки.
     *
     * @return array<int, string> Коды.
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Число попаданий.
     *
     * @return int|string Число или all.
     */
    public function getHitCount(): int|string
    {
        return $this->hitCount;
    }
}
