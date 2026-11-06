<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Dto\MapRoute;
use Mifrial\Roleplay\Map\Dto\MapTerrainRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Загружает граф варианта и собирает маршрут.
 */
final class MapRouteService
{
    /**
     * Создаёт сервис.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapVariantService $variantService Варианты.
     * @param MapNodeStore $nodeStore Узлы.
     * @param MapEdgeStore $edgeStore Рёбра.
     * @param MapTerrainService $terrainService Террейны.
     * @param MapRouteGraph $routeGraph Чистый граф.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapVariantService $variantService,
        private readonly MapNodeStore $nodeStore,
        private readonly MapEdgeStore $edgeStore,
        private readonly MapTerrainService $terrainService,
        private readonly MapRouteGraph $routeGraph,
    ) {
    }

    /**
     * Строит маршрут между узлами.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     * @param int $variantId Вариант.
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     *
     * @return MapRoute Маршрут.
     *
     * @throws MapInvalidException Если нет масштаба или узла.
     */
    public function route(
        RequestActor $actor,
        int $mapId,
        int $variantId,
        int $fromNodeId,
        int $toNodeId,
    ): MapRoute {
        $this->accessService->requireMapForView($actor, $mapId);
        $variantRecord = $this->variantService->requireVariant($mapId, $variantId);
        $placements = $this->indexPlacements($this->nodeStore->findPlacements($variantId));
        if (!isset($placements[$fromNodeId]) || !isset($placements[$toNodeId])) {
            throw new MapInvalidException('Map route node is not present in the variant');
        }

        $edges = $this->edgeStore->findEdges($variantId);
        $adjacency = $this->routeGraph->buildAdjacency(
            $edges,
            $placements,
            $this->indexTerrains($this->terrainService->getAll()),
            $variantRecord->getMillimetersPerPixel(),
        );
        $path = $this->routeGraph->shortestPath($adjacency, $fromNodeId, $toNodeId);
        return new MapRoute(
            $path['nodeIds'],
            $this->buildPoints($path['edgeIds'], $this->indexEdges($edges), $placements),
            $path['edgeIds'],
            $path['distance'],
            $path['cost'],
        );
    }

    /**
     * Индексирует размещения.
     *
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, MapPlacementRecord> Узел → размещение.
     */
    private function indexPlacements(array $placements): array
    {
        $indexed = [];
        foreach ($placements as $placementRecord) {
            if ($placementRecord->isPresent()) {
                $indexed[$placementRecord->getNodeId()] = $placementRecord;
            }
        }

        return $indexed;
    }

    /**
     * Индексирует террейны.
     *
     * @param array<int, MapTerrainRecord> $terrains Террейны.
     *
     * @return array<int, MapTerrainRecord> Id → террейн.
     */
    private function indexTerrains(array $terrains): array
    {
        $indexed = [];
        foreach ($terrains as $terrainRecord) {
            $indexed[$terrainRecord->getId()] = $terrainRecord;
        }

        return $indexed;
    }

    /**
     * Индексирует рёбра.
     *
     * @param array<int, MapEdgeRecord> $edges Рёбра.
     *
     * @return array<int, MapEdgeRecord> Id → ребро.
     */
    private function indexEdges(array $edges): array
    {
        $indexed = [];
        foreach ($edges as $edgeRecord) {
            $indexed[$edgeRecord->getId()] = $edgeRecord;
        }

        return $indexed;
    }

    /**
     * Строит полилинию маршрута.
     *
     * @param array<int, int> $edgeIds Рёбра по порядку.
     * @param array<int, MapEdgeRecord> $edgesById Рёбра по id.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    private function buildPoints(array $edgeIds, array $edgesById, array $placements): array
    {
        $points = [];
        foreach ($edgeIds as $edgeId) {
            $edgeRecord = $edgesById[$edgeId] ?? null;
            if ($edgeRecord instanceof MapEdgeRecord) {
                $points = $this->appendPoints($points, $this->edgePoints($edgeRecord, $placements));
            }
        }

        return $points;
    }

    /**
     * Добавляет точки без дубля на стыке.
     *
     * @param array<int, array{0: int, 1: int}> $points Накопленные точки.
     * @param array<int, array{0: int, 1: int}> $edgePoints Точки ребра.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    private function appendPoints(array $points, array $edgePoints): array
    {
        foreach ($edgePoints as $point) {
            if ($points === [] || $points[array_key_last($points)] !== $point) {
                $points[] = $point;
            }
        }

        return $points;
    }

    /**
     * Точки ребра в порядке from → to.
     *
     * @param MapEdgeRecord $edgeRecord Ребро.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    private function edgePoints(MapEdgeRecord $edgeRecord, array $placements): array
    {
        $fromPlacement = $placements[$edgeRecord->getFromNodeId()];
        $toPlacement = $placements[$edgeRecord->getToNodeId()];
        $path = $edgeRecord->getPath();
        if ($path === []) {
            return [
                [$fromPlacement->getX(), $fromPlacement->getY()],
                [$toPlacement->getX(), $toPlacement->getY()],
            ];
        }

        $first = $path[0];
        $last = $path[array_key_last($path)];
        $fromPoint = [$fromPlacement->getX(), $fromPlacement->getY()];

        return $this->pointDistance($last, $fromPoint) < $this->pointDistance($first, $fromPoint)
            ? array_reverse($path)
            : $path;
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
