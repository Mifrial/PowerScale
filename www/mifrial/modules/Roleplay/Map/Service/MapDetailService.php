<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
use Mifrial\Roleplay\Map\Dto\MapDetail;
use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Dto\MapVariantRecord;

/**
 * Собирает полный снимок карты без JSON-маппинга.
 */
final class MapDetailService
{
    /**
     * Создаёт сервис.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapVariantService $variantService Варианты.
     * @param MapNodeStore $nodeStore Узлы.
     * @param MapAreaStore $areaStore Области.
     * @param MapLineStore $lineStore Ленты.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapVariantService $variantService,
        private readonly MapNodeStore $nodeStore,
        private readonly MapAreaStore $areaStore,
        private readonly MapLineStore $lineStore,
    ) {
    }

    /**
     * Загружает карту.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     *
     * @return MapDetail Снимок.
     */
    public function load(RequestActor $actor, int $mapId): MapDetail
    {
        $mapRecord = $this->accessService->requireMapForView($actor, $mapId);
        $variants = $this->variantService->findVariants($mapId);
        $placements = [];
        $shapes = [];
        $lines = [];
        foreach ($variants as $variantRecord) {
            $placements = array_merge($placements, $this->nodeStore->findPlacements($variantRecord->getId()));
            $shapes = array_merge($shapes, $this->areaStore->findShapes($variantRecord->getId()));
            $lines = array_merge($lines, $this->lineStore->findLines($variantRecord->getId()));
        }

        return new MapDetail(
            $mapRecord,
            $variants,
            $this->nodeStore->findNodes($mapId),
            $placements,
            $this->areaStore->findAreas($mapId),
            $shapes,
            $lines,
            $this->accessService->canEdit($actor, $mapRecord),
        );
    }

    /**
     * Группирует размещения по узлу.
     *
     * @param array<int, MapPlacementRecord> $placements Размещения.
     *
     * @return array<int, array<int, MapPlacementRecord>> id узла → размещения.
     */
    public function groupPlacements(array $placements): array
    {
        $grouped = [];
        foreach ($placements as $placementRecord) {
            $grouped[$placementRecord->getNodeId()][] = $placementRecord;
        }

        return $grouped;
    }

    /**
     * Группирует полигоны по области.
     *
     * @param array<int, MapAreaShapeRecord> $shapes Полигоны.
     *
     * @return array<int, array<int, MapAreaShapeRecord>> id области → полигоны.
     */
    public function groupShapes(array $shapes): array
    {
        $grouped = [];
        foreach ($shapes as $shapeRecord) {
            $grouped[$shapeRecord->getAreaId()][] = $shapeRecord;
        }

        return $grouped;
    }

    /**
     * Ленты по варианту.
     *
     * @param array<int, MapLineRecord> $lines Ленты.
     *
     * @return array<int, array<int, MapLineRecord>> id варианта → ленты.
     */
    public function groupLines(array $lines): array
    {
        $grouped = [];
        foreach ($lines as $lineRecord) {
            $grouped[$lineRecord->getVariantId()][] = $lineRecord;
        }

        return $grouped;
    }

    /**
     * Варианты по id.
     *
     * @param array<int, MapVariantRecord> $variants Варианты.
     *
     * @return array<int, MapVariantRecord> id → вариант.
     */
    public function indexVariants(array $variants): array
    {
        $indexed = [];
        foreach ($variants as $variantRecord) {
            $indexed[$variantRecord->getId()] = $variantRecord;
        }

        return $indexed;
    }
}
