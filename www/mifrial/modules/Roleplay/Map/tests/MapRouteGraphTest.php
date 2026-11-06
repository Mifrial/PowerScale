<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Tests;

use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Dto\MapTerrainRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNoRouteException;
use Mifrial\Roleplay\Map\Service\MapRouteGraph;
use PHPUnit\Framework\TestCase;

final class MapRouteGraphTest extends TestCase
{
    private MapRouteGraph $graph;

    /**
     * Готовит граф.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->graph = new MapRouteGraph();
    }

    /**
     * Дорога дешевле леса при равном расстоянии.
     *
     * @return void
     */
    public function testChoosesCheaperTerrain(): void
    {
        $placements = [1 => $this->placement(1, 0, 0), 2 => $this->placement(2, 100, 0)];
        $terrains = [1 => $this->terrain(1, 'road', 100), 2 => $this->terrain(2, 'forest', 30)];
        $edges = [
            $this->edge(10, 1, 2, 1, [], 100, false),
            $this->edge(11, 1, 2, 2, [], 80, false),
        ];
        $adjacency = $this->graph->buildAdjacency($edges, $placements, $terrains, 1000);
        $path = $this->graph->shortestPath($adjacency, 1, 2);

        self::assertSame([10], $path['edgeIds']);
        self::assertSame([1, 2], $path['nodeIds']);
        self::assertSame(100, $path['distance']);
        self::assertSame(100, $path['cost']);
    }

    /**
     * Короткий лес может быть дешевле длинной дороги.
     *
     * @return void
     */
    public function testShortForestWins(): void
    {
        $placements = [1 => $this->placement(1, 0, 0), 2 => $this->placement(2, 100, 0)];
        $terrains = [1 => $this->terrain(1, 'road', 100), 2 => $this->terrain(2, 'forest', 30)];
        $edges = [
            $this->edge(10, 1, 2, 1, [], 100, false),
            $this->edge(11, 1, 2, 2, [], 10, false),
        ];
        $adjacency = $this->graph->buildAdjacency($edges, $placements, $terrains, 1000);
        $path = $this->graph->shortestPath($adjacency, 1, 2);

        self::assertSame([11], $path['edgeIds']);
        self::assertSame(33, $path['cost']);
    }

    /**
     * Террейн с нулевой скоростью непроходим.
     *
     * @return void
     */
    public function testBlockedTerrainHasNoRoute(): void
    {
        $this->expectException(MapNoRouteException::class);
        $placements = [1 => $this->placement(1, 0, 0), 2 => $this->placement(2, 100, 0)];
        $terrains = [1 => $this->terrain(1, 'water', 0)];
        $edges = [$this->edge(10, 1, 2, 1, [], 100, false)];
        $adjacency = $this->graph->buildAdjacency($edges, $placements, $terrains, 1000);
        $this->graph->shortestPath($adjacency, 1, 2);
    }

    /**
     * Направленное ребро не ходит назад.
     *
     * @return void
     */
    public function testDirectedEdgeDoesNotReverse(): void
    {
        $this->expectException(MapNoRouteException::class);
        $placements = [1 => $this->placement(1, 0, 0), 2 => $this->placement(2, 100, 0)];
        $terrains = [1 => $this->terrain(1, 'road', 100)];
        $edges = [$this->edge(10, 1, 2, 1, [], 100, true)];
        $adjacency = $this->graph->buildAdjacency($edges, $placements, $terrains, 1000);
        $this->graph->shortestPath($adjacency, 2, 1);
    }

    /**
     * Расстояние считается по масштабу, если не задано явно.
     *
     * @return void
     */
    public function testScaleComputesDistance(): void
    {
        $placements = [1 => $this->placement(1, 0, 0), 2 => $this->placement(2, 100, 0)];
        $terrains = [1 => $this->terrain(1, 'road', 100)];
        $edges = [$this->edge(10, 1, 2, 1, [], null, false)];
        $adjacency = $this->graph->buildAdjacency($edges, $placements, $terrains, 1000);
        $path = $this->graph->shortestPath($adjacency, 1, 2);

        self::assertSame(100, $path['distance']);
        self::assertSame(100, $path['cost']);
    }

    /**
     * Без масштаба и явного расстояния ребро не считается.
     *
     * @return void
     */
    public function testMissingScaleFails(): void
    {
        $this->expectException(MapInvalidException::class);
        $placements = [1 => $this->placement(1, 0, 0), 2 => $this->placement(2, 100, 0)];
        $terrains = [1 => $this->terrain(1, 'road', 100)];
        $edges = [$this->edge(10, 1, 2, 1, [], null, false)];
        $this->graph->buildAdjacency($edges, $placements, $terrains, null);
    }

    /**
     * Нет связи — нет маршрута.
     *
     * @return void
     */
    public function testDisconnectedHasNoRoute(): void
    {
        $this->expectException(MapNoRouteException::class);
        $placements = [
            1 => $this->placement(1, 0, 0),
            2 => $this->placement(2, 100, 0),
            3 => $this->placement(3, 200, 0),
        ];
        $terrains = [1 => $this->terrain(1, 'road', 100)];
        $edges = [$this->edge(10, 1, 2, 1, [], 100, false)];
        $adjacency = $this->graph->buildAdjacency($edges, $placements, $terrains, 1000);
        $this->graph->shortestPath($adjacency, 1, 3);
    }

    /**
     * Размещение.
     *
     * @param int $nodeId Узел.
     * @param int $x X.
     * @param int $y Y.
     *
     * @return MapPlacementRecord Размещение.
     */
    private function placement(int $nodeId, int $x, int $y): MapPlacementRecord
    {
        return MapPlacementRecord::fromNormalized([
            'id' => $nodeId,
            'variant_id' => 1,
            'node_id' => $nodeId,
            'x' => $x,
            'y' => $y,
            'is_present' => true,
            'label_override' => '',
        ]);
    }

    /**
     * Террейн.
     *
     * @param int $terrainId Id.
     * @param string $code Код.
     * @param int $speedFactor Скорость.
     *
     * @return MapTerrainRecord Террейн.
     */
    private function terrain(int $terrainId, string $code, int $speedFactor): MapTerrainRecord
    {
        return MapTerrainRecord::fromNormalized([
            'id' => $terrainId,
            'code' => $code,
            'label' => $code,
            'speed_factor' => $speedFactor,
            'color' => '#000000',
            'sort_order' => $terrainId,
        ]);
    }

    /**
     * Ребро.
     *
     * @param int $edgeId Id.
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     * @param int $terrainId Террейн.
     * @param array<int, array{0: int, 1: int}> $path Путь; пусто — прямая.
     * @param int|null $distanceMeters Расстояние.
     * @param bool $isDirected Направленное.
     *
     * @return MapEdgeRecord Ребро.
     */
    private function edge(
        int $edgeId,
        int $fromNodeId,
        int $toNodeId,
        int $terrainId,
        array $path,
        ?int $distanceMeters,
        bool $isDirected,
    ): MapEdgeRecord {
        return MapEdgeRecord::fromNormalized([
            'id' => $edgeId,
            'variant_id' => 1,
            'from_node_id' => $fromNodeId,
            'to_node_id' => $toNodeId,
            'terrain_id' => $terrainId,
            'path' => $path,
            'distance_meters' => $distanceMeters,
            'is_directed' => $isDirected,
            'label' => '',
        ]);
    }
}
