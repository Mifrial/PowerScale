<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Снимок карты для JSON; поля — данные, не порты.

namespace Mifrial\Roleplay\Map\Dto;

/**
 * Полный снимок карты для просмотра и редактора.
 */
final class MapDetail
{
    /**
     * Собирает снимок.
     *
     * @param MapRecord $mapRecord Карта.
     * @param array<int, MapVariantRecord> $variants Варианты.
     * @param array<int, MapNodeRecord> $nodes Узлы.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     * @param array<int, MapAreaRecord> $areas Области.
     * @param array<int, MapAreaShapeRecord> $shapes Полигоны.
     * @param array<int, MapEdgeRecord> $edges Рёбра.
     * @param array<int, MapLineRecord> $lines Ленты.
     * @param bool $canEdit Можно править.
     *
     * @return void
     */
    public function __construct(
        private readonly MapRecord $mapRecord,
        private readonly array $variants,
        private readonly array $nodes,
        private readonly array $placements,
        private readonly array $areas,
        private readonly array $shapes,
        private readonly array $edges,
        private readonly array $lines,
        private readonly bool $canEdit,
    ) {
    }

    /**
     * Карта.
     *
     * @return MapRecord Карта.
     */
    public function getMap(): MapRecord
    {
        return $this->mapRecord;
    }

    /**
     * Варианты.
     *
     * @return array<int, MapVariantRecord> Варианты.
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * Узлы.
     *
     * @return array<int, MapNodeRecord> Узлы.
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Размещения.
     *
     * @return array<int, MapPlacementRecord> Размещения.
     */
    public function getPlacements(): array
    {
        return $this->placements;
    }

    /**
     * Области.
     *
     * @return array<int, MapAreaRecord> Области.
     */
    public function getAreas(): array
    {
        return $this->areas;
    }

    /**
     * Полигоны.
     *
     * @return array<int, MapAreaShapeRecord> Полигоны.
     */
    public function getShapes(): array
    {
        return $this->shapes;
    }

    /**
     * Рёбра.
     *
     * @return array<int, MapEdgeRecord> Рёбра.
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * Ленты.
     *
     * @return array<int, MapLineRecord> Ленты.
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Можно править.
     *
     * @return bool Флаг.
     */
    public function canEdit(): bool
    {
        return $this->canEdit;
    }
}
