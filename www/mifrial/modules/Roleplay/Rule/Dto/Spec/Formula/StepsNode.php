<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * N собственных шагов персонажа.
 */
final class StepsNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param int $count Число шагов.
     *
     * @return void
     */
    public function __construct(
        private readonly int $count,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'steps';
    }

    /**
     * Число шагов.
     *
     * @return int Значение.
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
