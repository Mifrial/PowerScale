<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation;

/**
 * Ветка movement.
 */
final class MovementOperation implements ProcessOperation
{
    /**
     * Создаёт ветку.
     *
     * @param array<int, string> $horizontal Горизонтальные направления.
     * @param array<int, string> $vertical Вертикальные направления.
     * @param MovementAxisDistance|null $horizontalDistance Горизонтальная вилка.
     * @param MovementAxisDistance|null $verticalDistance Вертикальная вилка.
     * @param int|null $maxDegrees Свободный поворот.
     * @param bool $free Поворот свободен.
     *
     * @return void
     */
    public function __construct(
        private readonly array $horizontal,
        private readonly array $vertical,
        private readonly ?MovementAxisDistance $horizontalDistance,
        private readonly ?MovementAxisDistance $verticalDistance,
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
     * Горизонтальные направления.
     *
     * @return array<int, string> Значение.
     */
    public function getHorizontal(): array
    {
        return $this->horizontal;
    }

    /**
     * Вертикальные направления.
     *
     * @return array<int, string> Значение.
     */
    public function getVertical(): array
    {
        return $this->vertical;
    }

    /**
     * Горизонтальная вилка.
     *
     * @return MovementAxisDistance|null Значение.
     */
    public function findHorizontalDistance(): ?MovementAxisDistance
    {
        return $this->horizontalDistance;
    }

    /**
     * Вертикальная вилка.
     *
     * @return MovementAxisDistance|null Значение.
     */
    public function findVerticalDistance(): ?MovementAxisDistance
    {
        return $this->verticalDistance;
    }

    /**
     * Свободный поворот.
     *
     * @return int|null Значение.
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
