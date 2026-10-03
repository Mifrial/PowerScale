<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance;

/**
 * Ветка steps.
 */
final class StepsDistance implements ProcessDistance
{
    /**
     * Создаёт ветку.
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
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
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
