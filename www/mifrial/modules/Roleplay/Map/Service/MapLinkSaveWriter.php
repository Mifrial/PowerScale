<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Замена связок: размещения узлов и формы областей.
 */
final class MapLinkSaveWriter
{
    /**
     * Создаёт писатель.
     *
     * @param MapNodeStore $nodeStore Узлы и размещения.
     * @param MapAreaStore $areaStore Области и формы.
     *
     * @return void
     */
    public function __construct(
        private readonly MapNodeStore $nodeStore,
        private readonly MapAreaStore $areaStore,
    ) {
    }

    /**
     * Заменяет размещения узлов.
     *
     * @param array<int, int> $variantMap Id снимка → реальный id варианта.
     * @param array<int, array<string, mixed>> $nodes Узлы снимка.
     * @param array<int, int> $nodeMap Id снимка → реальный id узла.
     *
     * @return void
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    public function placements(array $variantMap, array $nodes, array $nodeMap): void
    {
        foreach ($variantMap as $snapshotVariantId => $realVariantId) {
            $this->nodeStore->deletePlacementsByVariant($realVariantId);
            foreach ($nodes as $node) {
                $this->placeNode(
                    $node,
                    $snapshotVariantId,
                    $realVariantId,
                    $this->mapped($nodeMap, $node['id']),
                );
            }
        }
    }

    /**
     * Заменяет формы областей.
     *
     * @param array<int, int> $variantMap Id снимка → реальный id варианта.
     * @param array<int, array<string, mixed>> $areas Области снимка.
     * @param array<int, int> $areaMap Id снимка → реальный id области.
     *
     * @return void
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    public function shapes(array $variantMap, array $areas, array $areaMap): void
    {
        foreach ($variantMap as $snapshotVariantId => $realVariantId) {
            $this->areaStore->deleteShapesByVariant($realVariantId);
            foreach ($areas as $area) {
                $this->shapeArea(
                    $area,
                    $snapshotVariantId,
                    $realVariantId,
                    $this->mapped($areaMap, $area['id']),
                );
            }
        }
    }

    /**
     * Размещения одного узла в варианте.
     *
     * @param array<string, mixed> $node Узел.
     * @param int $snapshotVariantId Вариант снимка.
     * @param int $realVariantId Реальный вариант.
     * @param int $realNodeId Реальный узел.
     *
     * @return void
     */
    private function placeNode(array $node, int $snapshotVariantId, int $realVariantId, int $realNodeId): void
    {
        foreach ($node['placements'] as $placement) {
            if ($placement['variantId'] !== $snapshotVariantId) {
                continue;
            }

            $this->nodeStore->upsertPlacement(
                $realVariantId,
                $realNodeId,
                $placement['x'],
                $placement['y'],
                $placement['isPresent'],
                $placement['labelOverride'],
            );
        }
    }

    /**
     * Формы одной области в варианте.
     *
     * @param array<string, mixed> $area Область.
     * @param int $snapshotVariantId Вариант снимка.
     * @param int $realVariantId Реальный вариант.
     * @param int $realAreaId Реальная область.
     *
     * @return void
     */
    private function shapeArea(array $area, int $snapshotVariantId, int $realVariantId, int $realAreaId): void
    {
        foreach ($area['shapes'] as $shape) {
            if ($shape['variantId'] !== $snapshotVariantId) {
                continue;
            }

            $this->areaStore->upsertShape(
                $realVariantId,
                $realAreaId,
                $shape['path'],
                $shape['handles'],
                $shape['isPresent'],
            );
        }
    }

    /**
     * Разрешает id снимка в реальный.
     *
     * @param array<int, int> $idMap Карта.
     * @param int $id Id снимка.
     *
     * @return int Реальный id.
     *
     * @throws MapInvalidException Если ссылки нет.
     */
    private function mapped(array $idMap, int $id): int
    {
        if (!isset($idMap[$id])) {
            throw new MapInvalidException('Map snapshot has an unresolved reference');
        }

        return $idMap[$id];
    }
}
