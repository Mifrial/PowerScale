<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
use Mifrial\Roleplay\Map\Dto\MapDetail;
use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Dto\MapNodeRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Dto\MapRecord;
use Mifrial\Roleplay\Map\Dto\MapRoute;
use Mifrial\Roleplay\Map\Dto\MapVariantRecord;

/**
 * JSON-представление карт, мест, областей, лент и маршрута.
 */
final class MapViewAssembler
{
    /**
     * Создаёт сборщик.
     *
     * @param IFiles $files Файлы подложек.
     *
     * @return void
     */
    public function __construct(
        private readonly IFiles $files,
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
        return $this->assembleSummary($detail->getMap()) + [
            'canEdit' => $detail->canEdit(),
            'variants' => $this->variantViews($detail),
            'nodes' => $this->nodeViews($detail),
            'areas' => $this->areaViews($detail),
            'lines' => $this->lineViews($detail),
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
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleArea(MapAreaRecord $areaRecord, array $shapes): array
    {
        $shapeViews = [];
        foreach ($shapes as $shapeRecord) {
            $shapeViews[] = [
                'variantId' => $shapeRecord->getVariantId(),
                'path' => $shapeRecord->getPath(),
                'handles' => $shapeRecord->getHandles(),
                'isPresent' => $shapeRecord->isPresent(),
            ];
        }

        return [
            'id' => $areaRecord->getId(),
            'layer' => $areaRecord->getLayer(),
            'color' => $areaRecord->getColor(),
            'speed' => $areaRecord->getSpeed(),
            'label' => $areaRecord->getLabel(),
            'nodeId' => $areaRecord->getNodeId(),
            'sortOrder' => $areaRecord->getSortOrder(),
            'shapes' => $shapeViews,
        ];
    }

    /**
     * Лента.
     *
     * @param MapLineRecord $lineRecord Лента.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleLine(MapLineRecord $lineRecord): array
    {
        return [
            'id' => $lineRecord->getId(),
            'variantId' => $lineRecord->getVariantId(),
            'color' => $lineRecord->getColor(),
            'speed' => $lineRecord->getSpeed(),
            'points' => $lineRecord->getPoints(),
            'widths' => $lineRecord->getWidths(),
            'handles' => $lineRecord->getHandles(),
            'segments' => $lineRecord->getSegments(),
            'label' => $lineRecord->getLabel(),
            'isPresent' => $lineRecord->isPresent(),
            'sortOrder' => $lineRecord->getSortOrder(),
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
            'points' => $route->getPoints(),
            'distancePixels' => $route->getDistancePixels(),
            'costPixels' => $route->getCostPixels(),
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
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function areaViews(MapDetail $detail): array
    {
        $shapes = $this->groupShapes($detail->getShapes());
        $views = [];
        foreach ($detail->getAreas() as $areaRecord) {
            $views[] = $this->assembleArea($areaRecord, $shapes[$areaRecord->getId()] ?? []);
        }

        return $views;
    }

    /**
     * Ленты в JSON.
     *
     * @param MapDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function lineViews(MapDetail $detail): array
    {
        $views = [];
        foreach ($detail->getLines() as $lineRecord) {
            $views[] = $this->assembleLine($lineRecord);
        }

        return $views;
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
