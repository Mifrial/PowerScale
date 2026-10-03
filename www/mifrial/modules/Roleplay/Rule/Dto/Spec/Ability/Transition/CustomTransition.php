<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition;

/**
 * Свой граф переходов.
 */
final class CustomTransition implements ProcessTransition
{
    /**
     * Создаёт переход.
     *
     * @param array<int, TransitionEdge> $edges Рёбра.
     * @param array<int, string> $exits Выходы.
     *
     * @return void
     */
    public function __construct(
        private readonly array $edges,
        private readonly array $exits,
    ) {
    }

    /**
     * Режим.
     *
     * @return string Код.
     */
    public function getMode(): string
    {
        return 'custom';
    }

    /**
     * Рёбра.
     *
     * @return array<int, TransitionEdge> Список.
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * Выходы.
     *
     * @return array<int, string> Коды.
     */
    public function getExits(): array
    {
        return $this->exits;
    }
}
