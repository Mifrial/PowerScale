<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation;

/**
 * Ветка turn.
 */
final class TurnOperation implements ProcessOperation
{
    /**
     * Создаёт ветку.
     *
     * @param int $maxDegrees Максимум градусов.
     *
     * @return void
     */
    public function __construct(
        private readonly int $maxDegrees,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'turn';
    }

    /**
     * Максимум градусов.
     *
     * @return int Значение.
     */
    public function getMaxDegrees(): int
    {
        return $this->maxDegrees;
    }
}
