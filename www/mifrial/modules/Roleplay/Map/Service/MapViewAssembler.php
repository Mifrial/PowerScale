<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
use Mifrial\Roleplay\Map\Dto\MapDetail;
use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Dto\MapNodeRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Dto\MapRecord;
use Mifrial\Roleplay\Map\Dto\MapRoute;
use Mifrial\Roleplay\Map\Dto\MapTerrainRecord;
use Mifrial\Roleplay\Map\Dto\MapVariantRecord;

/**
 * JSON-представление карт, точек, областей, лент, рёбер и маршрута.
 */
final class MapViewAssembler
{
    /**
     * Создаёт сборщик.
     *
     * @param IFiles $files Файлы подложек.
     * @param MapTerrainService $terrainService Справочник террейнов.
     *
     * @return void
     */
    public function __construct(
        private readonly IFiles $files,
        private readonly MapTerrainService $terrainService,
    ) {
    }

    /**
     * Краткая карта для списка.
     *
     * @param MapRecord $mapRecord Карта.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleSummary(MapRecord $mapRecord): array
    {
        return [
            'id' => $mapRecord->getId(),
            'ownerId' => $mapRecord->getOwnerId(),
            'name' => $mapRecord->getName(),
            'description' => $mapRecord->getDescription(),
            'visibility' => $mapRecord->getVisibility(),
            'isArchived' => $mapRecord->isArchived(),
            'version' => $mapRecord->getVersion(),
            'updatedBy' => $mapRecord->getUpdatedBy(),
            'createdAt' => $mapRecord->getCreatedAt()->toUnix(),
            'updatedAt' => $mapRecord->getUpdatedAt()->toUnix(),
        ];
    }

    /**
     * Полная карта.
     *
     * @param MapDetail $detail Снимок.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleDetail(MapDetail $detail): array
    {
        $codes = $this->terrainCodes();

        return $this->assembleSummary($detail->getMap()) + [
            'canEdit' => $detail->canEdit(),
            'variants' => $this->variantViews($detail),
            'nodes' => $this->nodeViews($detail),
            'areas' => $this->areaViews($detail, $codes),
            'lines' => $this->lineViews($detail, $codes),
            'edges' => $this->edgeViews($detail, $codes),
        ];
    }

    /**
     * Вариант.
     *
     * @param MapVariantRecord $variantRecord Вариант.
     * @param string|null $publicId Внешний id подложки.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleVariant(MapVariantRecord $variantRecord, ?string $publicId): array
    {
        $baseFile = null;
        if ($publicId !== null) {
            $baseFile = [
                'publicId' => $publicId,
                'width' => $variantRecord->getWidth(),
                'height' => $variantRecord->getHeight(),
            ];
        }

        return [
            'id' => $variantRecord->getId(),
            'label' => $variantRecord->getLabel(),
            'fromMinutes' => $variantRecord->getFromMinutes(),
            'sortOrder' => $variantRecord->getSortOrder(),
            'baseFile' => $baseFile,
            'millimetersPerPixel' => $variantRecord->getMillimetersPerPixel(),
            'isDefault' => $variantRecord->isDefault(),
        ];
    }

    /**
     * Узел.
     *
     * @param MapNodeRecord $nodeRecord Узел.
     * @param array<int, MapPlacementRecord> $placements Размещения.
     * @param bool $canEdit Можно ли видеть секреты.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleNode(MapNodeRecord $nodeRecord, array $placements, bool $canEdit): array
    {
        $view = [
            'id' => $nodeRecord->getId(),
            'isPoi' => $nodeRecord->isPoi(),
            'name' => $nodeRecord->getName(),
            'shortInfo' => $nodeRecord->getShortInfo(),
            'linkedMapId' => $nodeRecord->getLinkedMapId(),
            'icon' => $nodeRecord->getIcon(),
            'placements' => $this->placementViews($placements),
        ];
        if ($canEdit) {
            $view['publicInfo'] = $nodeRecord->getPublicInfo();
            $view['gmInfo'] = $nodeRecord->getGmInfo();
        }

        return $view;
    }

    /**
     * Область.
     *
     * @param MapAreaRecord $areaRecord Область.
     * @param array<int, MapAreaShapeRecord> $shapes Полигоны.
     * @param array<int, string> $codes Id террейна → код.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleArea(MapAreaRecord $areaRecord, array $shapes, array $codes): array
    {
        $shapeViews = [];
        foreach ($shapes as $shapeRecord) {
            $shapeViews[] = [
                'variantId' => $shapeRecord->getVariantId(),
                'path' => $shapeRecord->getPath(),
                'isPresent' => $shapeRecord->isPresent(),
            ];
        }

        $terrainId = $areaRecord->getTerrainId();

        return [
            'id' => $areaRecord->getId(),
            'layer' => $areaRecord->getLayer(),
            'terrainCode' => $terrainId === null ? null : ($codes[$terrainId] ?? null),
            'kind' => $areaRecord->getKind(),
            'label' => $areaRecord->getLabel(),
            'nodeId' => $areaRecord->getNodeId(),
            'blocksMovement' => $areaRecord->blocksMovement(),
            'shapes' => $shapeViews,
        ];
    }

    /**
     * Лента.
     *
     * @param MapLineRecord $lineRecord Лента.
     * @param array<int, string> $codes Id террейна → код.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleLine(MapLineRecord $lineRecord, array $codes): array
    {
        return [
            'id' => $lineRecord->getId(),
            'variantId' => $lineRecord->getVariantId(),
            'terrainCode' => $codes[$lineRecord->getTerrainId()] ?? null,
            'points' => $lineRecord->getPoints(),
            'widths' => $lineRecord->getWidths(),
            'handles' => $lineRecord->getHandles(),
            'label' => $lineRecord->getLabel(),
            'isPresent' => $lineRecord->isPresent(),
        ];
    }

    /**
     * Ребро.
     *
     * @param MapEdgeRecord $edgeRecord Ребро.
     * @param array<int, string> $codes Id террейна → код.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleEdge(MapEdgeRecord $edgeRecord, array $codes): array
    {
        return [
            'id' => $edgeRecord->getId(),
            'variantId' => $edgeRecord->getVariantId(),
            'fromNodeId' => $edgeRecord->getFromNodeId(),
            'toNodeId' => $edgeRecord->getToNodeId(),
            'terrainCode' => $codes[$edgeRecord->getTerrainId()] ?? null,
            'path' => $edgeRecord->getPath(),
            'distanceMeters' => $edgeRecord->getDistanceMeters(),
            'isDirected' => $edgeRecord->isDirected(),
            'label' => $edgeRecord->getLabel(),
        ];
    }

    /**
     * Террейн.
     *
     * @param MapTerrainRecord $terrainRecord Террейн.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleTerrain(MapTerrainRecord $terrainRecord): array
    {
        return [
            'id' => $terrainRecord->getId(),
            'code' => $terrainRecord->getCode(),
            'label' => $terrainRecord->getLabel(),
            'speedFactor' => $terrainRecord->getSpeedFactor(),
            'color' => $terrainRecord->getColor(),
            'sortOrder' => $terrainRecord->getSortOrder(),
        ];
    }

    /**
     * Маршрут.
     *
     * @param MapRoute $route Маршрут.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleRoute(MapRoute $route): array
    {
        return [
            'nodeIds' => $route->getNodeIds(),
            'points' => $route->getPoints(),
            'edgeIds' => $route->getEdgeIds(),
            'distanceMeters' => $route->getDistanceMeters(),
            'costMeters' => $route->getCostMeters(),
        ];
    }

    /**
     * Варианты в JSON.
     *
     * @param MapDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function variantViews(MapDetail $detail): array
    {
        $publicIds = $this->publicIds($detail->getVariants());
        $views = [];
        foreach ($detail->getVariants() as $variantRecord) {
            $views[] = $this->assembleVariant(
                $variantRecord,
                $publicIds[$variantRecord->getBaseFileId() ?? 0] ?? null,
            );
        }

        return $views;
    }

    /**
     * Узлы в JSON.
     *
     * @param MapDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function nodeViews(MapDetail $detail): array
    {
        $placements = $this->groupPlacements($detail->getPlacements());
        $views = [];
        foreach ($detail->getNodes() as $nodeRecord) {
            $views[] = $this->assembleNode(
                $nodeRecord,
                $placements[$nodeRecord->getId()] ?? [],
                $detail->canEdit(),
            );
        }

        return $views;
    }

    /**
     * Области в JSON.
     *
     * @param MapDetail $detail Снимок.
     * @param array<int, string> $codes Id террейна → код.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function areaViews(MapDetail $detail, array $codes): array
    {
        $shapes = $this->groupShapes($detail->getShapes());
        $views = [];
        foreach ($detail->getAreas() as $areaRecord) {
            $views[] = $this->assembleArea($areaRecord, $shapes[$areaRecord->getId()] ?? [], $codes);
        }

        return $views;
    }

    /**
     * Ленты в JSON.
     *
     * @param MapDetail $detail Снимок.
     * @param array<int, string> $codes Id террейна → код.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function lineViews(MapDetail $detail, array $codes): array
    {
        $views = [];
        foreach ($detail->getLines() as $lineRecord) {
            $views[] = $this->assembleLine($lineRecord, $codes);
        }

        return $views;
    }

    /**
     * Рёбра в JSON.
     *
     * @param MapDetail $detail Снимок.
     * @param array<int, string> $codes Id террейна → код.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function edgeViews(MapDetail $detail, array $codes): array
    {
        $views = [];
        foreach ($detail->getEdges() as $edgeRecord) {
            $views[] = $this->assembleEdge($edgeRecord, $codes);
        }

        return $views;
    }

    /**
     * Коды террейнов.
     *
     * @return array<int, string> Id → код.
     */
    private function terrainCodes(): array
    {
        $codes = [];
        foreach ($this->terrainService->getAll() as $terrainRecord) {
            $codes[$terrainRecord->getId()] = $terrainRecord->getCode();
        }

        return $codes;
    }

    /**
     * Группирует размещения.
     *
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, array<int, MapPlacementRecord>> Узел → размещения.
     */
    private function groupPlacements(array $placements): array
    {
        $grouped = [];
        foreach ($placements as $placementRecord) {
            $grouped[$placementRecord->getNodeId()][] = $placementRecord;
        }

        return $grouped;
    }

    /**
     * Группирует полигоны.
     *
     * @param array<int, MapAreaShapeRecord> $shapes Полигоны.
     *
     * @return array<int, array<int, MapAreaShapeRecord>> Область → полигоны.
     */
    private function groupShapes(array $shapes): array
    {
        $grouped = [];
        foreach ($shapes as $shapeRecord) {
            $grouped[$shapeRecord->getAreaId()][] = $shapeRecord;
        }

        return $grouped;
    }

    /**
     * Внешние id подложек.
     *
     * @param array<int, MapVariantRecord> $variants Варианты.
     *
     * @return array<int, string> File id → public_id.
     */
    private function publicIds(array $variants): array
    {
        $fileIds = [];
        foreach ($variants as $variantRecord) {
            $fileId = $variantRecord->getBaseFileId();
            if ($fileId !== null) {
                $fileIds[] = $fileId;
            }
        }

        return $fileIds === [] ? [] : $this->files->activePublicIds($fileIds);
    }

    /**
     * Размещения в JSON.
     *
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function placementViews(array $placements): array
    {
        $views = [];
        foreach ($placements as $placementRecord) {
            $views[] = [
                'variantId' => $placementRecord->getVariantId(),
                'x' => $placementRecord->getX(),
                'y' => $placementRecord->getY(),
                'isPresent' => $placementRecord->isPresent(),
                'labelOverride' => $placementRecord->getLabelOverride(),
            ];
        }

        return $views;
    }
}
