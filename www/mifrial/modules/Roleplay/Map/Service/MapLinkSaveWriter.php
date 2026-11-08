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
     * Сколько полигонов вставлять одним multi-insert.
     */
    private const INSERT_CHUNK = 500;

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
            $this->writeVariantShapes($snapshotVariantId, $realVariantId, $areas, $areaMap);
        }
    }

    /**
     * Пишет формы одного варианта: новое пачкой, изменённое точечно,
     * исчезнувшее — удаляет.
     *
     * @param int $snapshotVariantId Вариант снимка.
     * @param int $realVariantId Реальный вариант.
     * @param array<int, array<string, mixed>> $areas Области снимка.
     * @param array<int, int> $areaMap Id снимка → реальный id области.
     *
     * @return void
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function writeVariantShapes(int $snapshotVariantId, int $realVariantId, array $areas, array $areaMap): void
    {
        $existing = $this->areaStore->findShapes($realVariantId);
        $existingByArea = [];
        foreach ($existing as $shapeRecord) {
            $existingByArea[$shapeRecord->getAreaId()] = $shapeRecord;
        }

        $inserts = [];
        $keptAreaIds = [];
        foreach ($areas as $area) {
            foreach ($area['shapes'] as $shape) {
                if ($shape['variantId'] !== $snapshotVariantId) {
                    continue;
                }

                $realAreaId = $this->mapped($areaMap, $area['id']);
                $keptAreaIds[$realAreaId] = true;
                $current = $existingByArea[$realAreaId] ?? null;
                if ($current === null) {
                    $inserts[] = [
                        'variantId' => $realVariantId,
                        'areaId' => $realAreaId,
                        'path' => $shape['path'],
                        'handles' => $shape['handles'],
                        'isPresent' => $shape['isPresent'],
                    ];

                    continue;
                }

                if (
                    $current->getPath() === $shape['path']
                    && $current->getHandles() === $shape['handles']
                    && $current->isPresent() === $shape['isPresent']
                ) {
                    continue;
                }

                $this->areaStore->updateShape($current->getId(), $shape['path'], $shape['handles'], $shape['isPresent']);
            }
        }

        foreach ($existing as $shapeRecord) {
            if (!isset($keptAreaIds[$shapeRecord->getAreaId()])) {
                $this->areaStore->deleteShape($shapeRecord->getId());
            }
        }

        foreach (array_chunk($inserts, self::INSERT_CHUNK) as $chunk) {
            $this->areaStore->addShapes($chunk);
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
