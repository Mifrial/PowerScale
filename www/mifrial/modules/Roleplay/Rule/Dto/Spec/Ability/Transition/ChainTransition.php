<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition;

/**
 * Цепочка шагов со сдвигом.
 */
final class ChainTransition implements ProcessTransition
{
    /**
     * Создаёт переход.
     *
     * @param int $maxShift Максимальный сдвиг.
     * @param string|null $direction forward или both.
     *
     * @return void
     */
    public function __construct(
        private readonly int $maxShift,
        private readonly ?string $direction,
    ) {
    }

    /**
     * Режим.
     *
     * @return string Код.
     */
    public function getMode(): string
    {
        return 'chain';
    }

    /**
     * Максимальный сдвиг.
     *
     * @return int Число.
     */
    public function getMaxShift(): int
    {
        return $this->maxShift;
    }

    /**
     * Направление.
     *
     * @return string|null Код или null.
     */
    public function getDirection(): ?string
    {
        return $this->direction;
    }
}
