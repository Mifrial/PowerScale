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
     * @param array<int, int> $nodeIds Узлы.
     * @param array<int, array{0: int, 1: int}> $points Точки.
     * @param array<int, int> $edgeIds Рёбра.
     * @param int $distanceMeters Расстояние.
     * @param int $costMeters Эффективное расстояние.
     *
     * @return void
     */
    public function __construct(
        private readonly array $nodeIds,
        private readonly array $points,
        private readonly array $edgeIds,
        private readonly int $distanceMeters,
        private readonly int $costMeters,
    ) {
    }

    /**
     * Узлы.
     *
     * @return array<int, int> Id.
     */
    public function getNodeIds(): array
    {
        return $this->nodeIds;
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
     * Рёбра.
     *
     * @return array<int, int> Id.
     */
    public function getEdgeIds(): array
    {
        return $this->edgeIds;
    }

    /**
     * Расстояние.
     *
     * @return int Метры.
     */
    public function getDistanceMeters(): int
    {
        return $this->distanceMeters;
    }

    /**
     * Эффективное расстояние.
     *
     * @return int Метры.
     */
    public function getCostMeters(): int
    {
        return $this->costMeters;
    }
}
