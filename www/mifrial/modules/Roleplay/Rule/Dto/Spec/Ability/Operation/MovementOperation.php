<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance\ProcessDistance;

/**
 * Ветка movement.
 */
final class MovementOperation implements ProcessOperation
{
    /**
     * Создаёт ветку.
     *
     * @param array $horizontal Горизонталь.
     * @param array $vertical Вертикаль.
     * @param ?ProcessDistance $distance Дистанция.
     * @param ?int $maxDegrees Свободный поворот.
     * @param bool $free Поворот свободен.
     *
     * @return void
     */
    public function __construct(
        private readonly array $horizontal,
        private readonly array $vertical,
        private readonly ?ProcessDistance $distance,
        private readonly ?int $maxDegrees,
        private readonly bool $free,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'movement';
    }

    /**
     * Горизонталь.
     *
     * @return array Значение.
     */
    public function getHorizontal(): array
    {
        return $this->horizontal;
    }

    /**
     * Вертикаль.
     *
     * @return array Значение.
     */
    public function getVertical(): array
    {
        return $this->vertical;
    }

    /**
     * Дистанция.
     *
     * @return ?ProcessDistance Значение.
     */
    public function getDistance(): ?ProcessDistance
    {
        return $this->distance;
    }

    /**
     * Свободный поворот.
     *
     * @return ?int Значение.
     */
    public function getMaxDegrees(): ?int
    {
        return $this->maxDegrees;
    }

    /**
     * Поворот свободен.
     *
     * @return bool Значение.
     */
    public function isFree(): bool
    {
        return $this->free;
    }
}
