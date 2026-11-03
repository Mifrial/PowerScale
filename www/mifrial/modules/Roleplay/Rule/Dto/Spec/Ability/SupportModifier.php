<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Реакция поддержки: какие стороны можно поддержать и насколько.
 */
final class SupportModifier
{
    /**
     * Создаёт узел поддержки.
     *
     * @param array<int, string> $sides Кому можно помочь: attacker или defender.
     * @param int $advantage Преимущество на проверку выбранной стороны.
     *
     * @return void
     */
    public function __construct(
        private readonly array $sides,
        private readonly int $advantage,
    ) {
    }

    /**
     * Разрешённые стороны.
     *
     * @return array<int, string> attacker и/или defender.
     */
    public function getSides(): array
    {
        return $this->sides;
    }

    /**
     * Преимущество.
     *
     * @return int Кубы.
     */
    public function getAdvantage(): int
    {
        return $this->advantage;
    }
}
