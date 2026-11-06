<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
use Mifrial\Roleplay\Map\Dto\MapDetail;
use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;

/**
 * Предупреждения о пересечении рёбер с блокерами.
 *
 * Публикацию не отменяет: мост через реку и арка в доме остаются на совести
 * автора, а маршрут считает только террейн ребра.
 */
final class MapBlockingCheck
{
    /**
     * Создаёт проверку.
     *
     * @param MapTerrainService $terrainService Справочник террейнов.
     * @param MapGeometry $geometry Геометрия.
     *
     * @return void
     */
    public function __construct(
        private readonly MapTerrainService $terrainService,
        private readonly MapGeometry $geometry,
    ) {
    }

    /**
     * Предупреждения по снимку.
     *
     * @param MapDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> Предупреждения.
     */
    public function check(MapDetail $detail): array
    {
        $speedById = $this->speedById();
        $areas = $this->areasById($detail->getAreas());
        $shapesByVariant = $this->shapesByVariant($detail->getShapes());
        $linesByVariant = $this->linesByVariant($detail->getLines());
        $placements = $this->placementIndex($detail->getPlacements());

        $warnings = [];
        foreach ($detail->getEdges() as $edgeRecord) {
            $edgePoints = $this->edgePoints($edgeRecord, $placements);
            if ($edgePoints === null || ($speedById[$edgeRecord->getTerrainId()] ?? 0) === 0) {
                continue;
            }

            $blocks = $this->blocksFor($edgeRecord->getVariantId(), $areas, $shapesByVariant, $speedById);
            $lines = $this->blockingLines($linesByVariant[$edgeRecord->getVariantId()] ?? [], $speedById);
            $warnings = array_merge(
                $warnings,
                $this->edgeWarnings($edgeRecord, $edgePoints, $blocks, $lines),
            );
        }

        return $warnings;
    }

    /**
     * Предупреждения одного ребра.
     *
     * @param MapEdgeRecord $edgeRecord Ребро.
     * @param array<int, array{0: int, 1: int}> $edgePoints Точки ребра.
     * @param array<int, array{id: int, path: array<int, array{0: int, 1: int}>}> $blocks Блокеры.
     * @param array<int, MapLineRecord> $lines Ленты варианта.
     *
     * @return array<int, array<string, mixed>> Предупреждения.
     */
    private function edgeWarnings(
        MapEdgeRecord $edgeRecord,
        array $edgePoints,
        array $blocks,
        array $lines,
    ): array {
        $warnings = [];
        foreach ($blocks as $blocker) {
            if ($this->geometry->polylineIntersectsPolygon($edgePoints, $blocker['path'])) {
                $warnings[] = $this->warning($edgeRecord->getId(), 'area', $blocker['id']);
            }
        }

        foreach ($lines as $lineRecord) {
            [$ribbonPoints, $ribbonWidths] = $this->geometry->flattenRibbon(
                $lineRecord->getPoints(),
                $lineRecord->getHandles(),
                $lineRecord->getWidths(),
            );
            if ($this->geometry->polylineIntersectsRibbon($edgePoints, $ribbonPoints, $ribbonWidths)) {
                $warnings[] = $this->warning($edgeRecord->getId(), 'line', $lineRecord->getId());
            }
        }

        return $warnings;
    }

    /**
     * Блокеры варианта: непроходимые области и объекты с запретом хода.
     *
     * @param int $variantId Вариант.
     * @param array<int, MapAreaRecord> $areas Области по id.
     * @param array<int, array<int, MapAreaShapeRecord>> $shapesByVariant Формы по варианту.
     * @param array<int, int> $speedById Скорость террейна.
     *
     * @return array<int, array{id: int, path: array<int, array{0: int, 1: int}>}> Блокеры.
     */
    private function blocksFor(
        int $variantId,
        array $areas,
        array $shapesByVariant,
        array $speedById,
    ): array {
        $blocks = [];
        foreach ($shapesByVariant[$variantId] ?? [] as $shapeRecord) {
            if (!$shapeRecord->isPresent()) {
                continue;
            }

            $areaRecord = $areas[$shapeRecord->getAreaId()] ?? null;
            if ($areaRecord === null || !$this->isBlocker($areaRecord, $speedById)) {
                continue;
            }

            $blocks[] = ['id' => $areaRecord->getId(), 'path' => $shapeRecord->getPath()];
        }

        return $blocks;
    }

    /**
     * Ленты с нулевой скоростью.
     *
     * @param array<int, MapLineRecord> $lines Ленты варианта.
     * @param array<int, int> $speedById Скорость террейна.
     *
     * @return array<int, MapLineRecord> Блокирующие ленты.
     */
    private function blockingLines(array $lines, array $speedById): array
    {
        $blocking = [];
        foreach ($lines as $lineRecord) {
            if (($speedById[$lineRecord->getTerrainId()] ?? 0) === 0) {
                $blocking[] = $lineRecord;
            }
        }

        return $blocking;
    }

    /**
     * Область непроходима.
     *
     * @param MapAreaRecord $areaRecord Область.
     * @param array<int, int> $speedById Скорость террейна.
     *
     * @return bool Флаг.
     */
    private function isBlocker(MapAreaRecord $areaRecord, array $speedById): bool
    {
        if ($areaRecord->getLayer() === 'terrain') {
            $terrainId = $areaRecord->getTerrainId();

            return $terrainId !== null && ($speedById[$terrainId] ?? 0) === 0;
        }

        return $areaRecord->blocksMovement();
    }

    /**
     * Полилиния ребра; пустой путь — прямая между размещениями.
     *
     * @param MapEdgeRecord $edgeRecord Ребро.
     * @param array<int, array<int, MapPlacementRecord>> $placements Вариант → узел → размещение.
     *
     * @return array<int, array{0: int, 1: int}>|null Точки или null.
     */
    private function edgePoints(MapEdgeRecord $edgeRecord, array $placements): ?array
    {
        if ($edgeRecord->getPath() !== []) {
            return $edgeRecord->getPath();
        }

        $variantId = $edgeRecord->getVariantId();
        $from = $placements[$variantId][$edgeRecord->getFromNodeId()] ?? null;
        $to = $placements[$variantId][$edgeRecord->getToNodeId()] ?? null;
        if ($from === null || $to === null) {
            return null;
        }

        return [[$from->getX(), $from->getY()], [$to->getX(), $to->getY()]];
    }

    /**
     * Скорость по id террейна.
     *
     * @return array<int, int> Id → процент.
     */
    private function speedById(): array
    {
        $speedById = [];
        foreach ($this->terrainService->getAll() as $terrainRecord) {
            $speedById[$terrainRecord->getId()] = $terrainRecord->getSpeedFactor();
        }

        return $speedById;
    }

    /**
     * Области по id.
     *
     * @param array<int, MapAreaRecord> $areas Области.
     *
     * @return array<int, MapAreaRecord> Id → область.
     */
    private function areasById(array $areas): array
    {
        $indexed = [];
        foreach ($areas as $areaRecord) {
            $indexed[$areaRecord->getId()] = $areaRecord;
        }

        return $indexed;
    }

    /**
     * Формы по варианту.
     *
     * @param array<int, MapAreaShapeRecord> $shapes Формы.
     *
     * @return array<int, array<int, MapAreaShapeRecord>> Вариант → формы.
     */
    private function shapesByVariant(array $shapes): array
    {
        $grouped = [];
        foreach ($shapes as $shapeRecord) {
            $grouped[$shapeRecord->getVariantId()][] = $shapeRecord;
        }

        return $grouped;
    }

    /**
     * Ленты по варианту.
     *
     * @param array<int, MapLineRecord> $lines Ленты.
     *
     * @return array<int, array<int, MapLineRecord>> Вариант → ленты.
     */
    private function linesByVariant(array $lines): array
    {
        $grouped = [];
        foreach ($lines as $lineRecord) {
            $grouped[$lineRecord->getVariantId()][] = $lineRecord;
        }

        return $grouped;
    }

    /**
     * Размещения по варианту и узлу.
     *
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, array<int, MapPlacementRecord>> Вариант → узел → размещение.
     */
    private function placementIndex(array $placements): array
    {
        $indexed = [];
        foreach ($placements as $placementRecord) {
            $indexed[$placementRecord->getVariantId()][$placementRecord->getNodeId()] = $placementRecord;
        }

        return $indexed;
    }

    /**
     * Одно предупреждение.
     *
     * @param int $edgeId Ребро.
     * @param string $blockerKind area|line.
     * @param int $blockerId Id блокера.
     *
     * @return array<string, mixed> Предупреждение.
     */
    private function warning(int $edgeId, string $blockerKind, int $blockerId): array
    {
        return [
            'code' => 'MAP_EDGE_CROSSES_BLOCKER',
            'edgeId' => $edgeId,
            'blockerKind' => $blockerKind,
            'blockerId' => $blockerId,
        ];
    }
}
