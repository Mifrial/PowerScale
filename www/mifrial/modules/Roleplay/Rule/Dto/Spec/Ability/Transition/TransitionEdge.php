<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition;

/**
 * Ребро своего графа шагов.
 */
final class TransitionEdge
{
    /**
     * Создаёт ребро.
     *
     * @param string $from Откуда.
     * @param string $to Куда.
     *
     * @return void
     */
    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {
    }

    /**
     * Откуда.
     *
     * @return string Код шага.
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Куда.
     *
     * @return string Код шага.
     */
    public function getTo(): string
    {
        return $this->to;
    }
}
