<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Dto\MapTerrainRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNoRouteException;

/**
 * Чистый граф маршрута: смежность, Dijkstra и расстояние ребра.
 */
final class MapRouteGraph
{
    /**
     * Строит список смежности.
     *
     * @param array<int, MapEdgeRecord> $edges Рёбра.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     * @param array<int, MapTerrainRecord> $terrains Террейны.
     * @param int|null $millimetersPerPixel Масштаб.
     *
     * @return array<int, array<int, array{to: int, edgeId: int, distance: int, cost: int}>> Смежность.
     *
     * @throws MapInvalidException Если расстояние не посчитать.
     */
    public function buildAdjacency(
        array $edges,
        array $placements,
        array $terrains,
        ?int $millimetersPerPixel,
    ): array {
        $adjacency = [];
        $this->appendEdges($adjacency, $edges, $placements, $terrains, $millimetersPerPixel);

        return $adjacency;
    }

    /**
     * Ищет кратчайший путь по стоимости.
     *
     * @param array<int, array<int, array{to: int, edgeId: int, distance: int, cost: int}>> $adjacency Смежность.
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     *
     * @return array{nodeIds: array<int, int>, edgeIds: array<int, int>, distance: int, cost: int} Путь.
     *
     * @throws MapNoRouteException Если пути нет.
     */
    public function shortestPath(array $adjacency, int $fromNodeId, int $toNodeId): array
    {
        $costs = [$fromNodeId => 0];
        $distances = [$fromNodeId => 0];
        $previous = $this->emptyPrevious();
        $unvisited = [$fromNodeId => true];
        $this->walk($adjacency, $toNodeId, $costs, $distances, $previous, $unvisited);
        if (!isset($costs[$toNodeId])) {
            throw new MapNoRouteException();
        }

        return $this->reconstruct($toNodeId, $previous, $distances, $costs);
    }

    /**
     * Обходит граф до цели.
     *
     * @param array<int, array<int, array{to: int, edgeId: int, distance: int, cost: int}>> $adjacency Смежность.
     * @param int $toNodeId Конец.
     * @param array<int, int> $costs Стоимости.
     * @param array<int, int> $distances Расстояния.
     * @param array<int, array{node: int, edge: int}> $previous Предыдущие шаги.
     * @param array<int, true> $unvisited Непосещённые.
     *
     * @return void
     */
    private function walk(
        array $adjacency,
        int $toNodeId,
        array &$costs,
        array &$distances,
        array &$previous,
        array &$unvisited,
    ): void {
        while ($unvisited !== []) {
            $current = $this->nearestNode($unvisited, $costs);
            if ($current === null) {
                break;
            }

            unset($unvisited[$current]);
            if ($current === $toNodeId) {
                break;
            }

            $this->relax($adjacency[$current] ?? [], $current, $costs, $distances, $previous, $unvisited);
        }
    }

    /**
     * Пустая карта предыдущих шагов.
     *
     * @return array<int, array{node: int, edge: int}> Карта.
     */
    private function emptyPrevious(): array
    {
        return [];
    }

    /**
     * Добавляет все проходимые рёбра.
     *
     * @param array<int, array<int, array{to: int, edgeId: int, distance: int, cost: int}>> $adjacency Смежность.
     * @param array<int, MapEdgeRecord> $edges Рёбра.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     * @param array<int, MapTerrainRecord> $terrains Террейны.
     * @param int|null $millimetersPerPixel Масштаб.
     *
     * @return void
     *
     * @throws MapInvalidException Если расстояние не посчитать.
     */
    private function appendEdges(
        array &$adjacency,
        array $edges,
        array $placements,
        array $terrains,
        ?int $millimetersPerPixel,
    ): void {
        foreach ($edges as $edgeRecord) {
            $this->appendEdge($adjacency, $edgeRecord, $placements, $terrains, $millimetersPerPixel);
        }
    }

    /**
     * Добавляет прямое и обратное ребро.
     *
     * @param array<int, array<int, array{to: int, edgeId: int, distance: int, cost: int}>> $adjacency Смежность.
     * @param MapEdgeRecord $edgeRecord Ребро.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     * @param array<int, MapTerrainRecord> $terrains Террейны.
     * @param int|null $millimetersPerPixel Масштаб.
     *
     * @return void
     *
     * @throws MapInvalidException Если расстояние не посчитать.
     */
    private function appendEdge(
        array &$adjacency,
        MapEdgeRecord $edgeRecord,
        array $placements,
        array $terrains,
        ?int $millimetersPerPixel,
    ): void {
        $terrainRecord = $terrains[$edgeRecord->getTerrainId()] ?? null;
        if (!$terrainRecord instanceof MapTerrainRecord || $terrainRecord->getSpeedFactor() <= 0) {
            return;
        }

        $fromNodeId = $edgeRecord->getFromNodeId();
        $toNodeId = $edgeRecord->getToNodeId();
        if (!isset($placements[$fromNodeId]) || !isset($placements[$toNodeId])) {
            return;
        }

        $distance = $this->edgeDistance($edgeRecord, $placements, $millimetersPerPixel);
        $cost = (int) round($distance * 100 / $terrainRecord->getSpeedFactor());
        $this->appendLink($adjacency, $fromNodeId, $toNodeId, $edgeRecord->getId(), $distance, $cost);
        if (!$edgeRecord->isDirected()) {
            $this->appendLink($adjacency, $toNodeId, $fromNodeId, $edgeRecord->getId(), $distance, $cost);
        }
    }

    /**
     * Добавляет одно направление.
     *
     * @param array<int, array<int, array{to: int, edgeId: int, distance: int, cost: int}>> $adjacency Смежность.
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     * @param int $edgeId Ребро.
     * @param int $distance Расстояние.
     * @param int $cost Стоимость.
     *
     * @return void
     */
    private function appendLink(
        array &$adjacency,
        int $fromNodeId,
        int $toNodeId,
        int $edgeId,
        int $distance,
        int $cost,
    ): void {
        $adjacency[$fromNodeId][] = [
            'to' => $toNodeId,
            'edgeId' => $edgeId,
            'distance' => $distance,
            'cost' => $cost,
        ];
    }

    /**
     * Ослабляет соседей.
     *
     * @param array<int, array{to: int, edgeId: int, distance: int, cost: int}> $links Соседи.
     * @param int $current Текущий узел.
     * @param array<int, int> $costs Стоимости.
     * @param array<int, int> $distances Расстояния.
     * @param array<int, array{node: int, edge: int}> $previous Предыдущие шаги.
     * @param array<int, true> $unvisited Непосещённые.
     *
     * @return void
     */
    private function relax(
        array $links,
        int $current,
        array &$costs,
        array &$distances,
        array &$previous,
        array &$unvisited,
    ): void {
        foreach ($links as $link) {
            $next = $link['to'];
            $candidateCost = $costs[$current] + $link['cost'];
            if (isset($costs[$next]) && $costs[$next] <= $candidateCost) {
                continue;
            }

            $costs[$next] = $candidateCost;
            $distances[$next] = $distances[$current] + $link['distance'];
            $previous[$next] = ['node' => $current, 'edge' => $link['edgeId']];
            $unvisited[$next] = true;
        }
    }

    /**
     * Ближайший непосещённый узел.
     *
     * @param array<int, true> $unvisited Непосещённые.
     * @param array<int, int> $costs Стоимости.
     *
     * @return int|null Id или null.
     */
    private function nearestNode(array $unvisited, array $costs): ?int
    {
        $nearest = null;
        $best = PHP_INT_MAX;
        foreach (array_keys($unvisited) as $nodeId) {
            if (isset($costs[$nodeId]) && $costs[$nodeId] < $best) {
                $best = $costs[$nodeId];
                $nearest = $nodeId;
            }
        }

        return $nearest;
    }

    /**
     * Восстанавливает путь.
     *
     * @param int $toNodeId Конец.
     * @param array<int, array{node: int, edge: int}> $previous Предыдущие шаги.
     * @param array<int, int> $distances Расстояния.
     * @param array<int, int> $costs Стоимости.
     *
     * @return array{nodeIds: array<int, int>, edgeIds: array<int, int>, distance: int, cost: int} Путь.
     */
    private function reconstruct(
        int $toNodeId,
        array $previous,
        array $distances,
        array $costs,
    ): array {
        $nodeIds = [$toNodeId];
        $edgeIds = [];
        $current = $toNodeId;
        while (isset($previous[$current])) {
            $edgeIds[] = $previous[$current]['edge'];
            $current = $previous[$current]['node'];
            $nodeIds[] = $current;
        }

        return [
            'nodeIds' => array_reverse($nodeIds),
            'edgeIds' => array_reverse($edgeIds),
            'distance' => $distances[$toNodeId],
            'cost' => $costs[$toNodeId],
        ];
    }

    /**
     * Расстояние ребра в метрах.
     *
     * @param MapEdgeRecord $edgeRecord Ребро.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     * @param int|null $millimetersPerPixel Масштаб.
     *
     * @return int Метры.
     *
     * @throws MapInvalidException Если нет масштаба.
     */
    private function edgeDistance(
        MapEdgeRecord $edgeRecord,
        array $placements,
        ?int $millimetersPerPixel,
    ): int {
        $explicit = $edgeRecord->getDistanceMeters();
        if ($explicit !== null) {
            return $explicit;
        }

        if ($millimetersPerPixel === null) {
            throw new MapInvalidException('Map scale is not calibrated');
        }

        $points = $edgeRecord->getPath();
        if ($points === []) {
            $points = $this->straightPoints($edgeRecord, $placements);
        }

        $pixels = 0.0;
        for ($index = 1; $index < count($points); ++$index) {
            $pixels += $this->pointDistance($points[$index - 1], $points[$index]);
        }

        return (int) round($pixels * $millimetersPerPixel / 1000);
    }

    /**
     * Прямая между узлами.
     *
     * @param MapEdgeRecord $edgeRecord Ребро.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    private function straightPoints(MapEdgeRecord $edgeRecord, array $placements): array
    {
        $fromPlacement = $placements[$edgeRecord->getFromNodeId()];
        $toPlacement = $placements[$edgeRecord->getToNodeId()];

        return [
            [$fromPlacement->getX(), $fromPlacement->getY()],
            [$toPlacement->getX(), $toPlacement->getY()],
        ];
    }

    /**
     * Евклидово расстояние между точками.
     *
     * @param array{0: int, 1: int} $left Точка.
     * @param array{0: int, 1: int} $right Точка.
     *
     * @return float Пиксели.
     */
    private function pointDistance(array $left, array $right): float
    {
        return sqrt(($left[0] - $right[0]) ** 2 + ($left[1] - $right[1]) ** 2);
    }
}
