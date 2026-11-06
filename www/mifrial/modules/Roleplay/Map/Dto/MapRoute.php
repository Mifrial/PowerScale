<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

/**
 * Результат маршрута по карте.
 */
final class MapRoute
{
    /**
     * Собирает маршрут.
     *
     * @param array<int, array{0: int, 1: int}> $points Точки.
     * @param float $distancePixels Длина пути в пикселях.
     * @param float $costPixels Стоимость пути в пикселях.
     * @param int|null $distanceMeters Расстояние или null без масштаба.
     * @param int|null $costMeters Стоимость или null без масштаба.
     *
     * @return void
     */
    public function __construct(
        private readonly array $points,
        private readonly float $distancePixels,
        private readonly float $costPixels,
        private readonly ?int $distanceMeters,
        private readonly ?int $costMeters,
    ) {
    }

    /**
     * Точки.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * Длина пути.
     *
     * @return float Пиксели.
     */
    public function getDistancePixels(): float
    {
        return $this->distancePixels;
    }

    /**
     * Стоимость пути.
     *
     * @return float Пиксели.
     */
    public function getCostPixels(): float
    {
        return $this->costPixels;
    }

    /**
     * Расстояние.
     *
     * @return int|null Метры или null.
     */
    public function getDistanceMeters(): ?int
    {
        return $this->distanceMeters;
    }

    /**
     * Стоимость.
     *
     * @return int|null Метры или null.
     */
    public function getCostMeters(): ?int
    {
        return $this->costMeters;
    }
}
