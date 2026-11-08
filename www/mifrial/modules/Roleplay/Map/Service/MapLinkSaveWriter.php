<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
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
        $existingByArea = $this->indexShapes($existing);
        $keptAreaIds = [];
        $inserts = $this->syncShapes(
            $areas,
            $snapshotVariantId,
            $realVariantId,
            $areaMap,
            $existingByArea,
            $keptAreaIds,
        );

        $this->deleteAbsentShapes($existing, $keptAreaIds);
        $this->insertShapes($inserts);
    }

    /**
     * Существующие формы по области.
     *
     * @param array<int, MapAreaShapeRecord> $shapes Записи.
     *
     * @return array<int, MapAreaShapeRecord> Id области → форма.
     */
    private function indexShapes(array $shapes): array
    {
        $indexed = [];
        foreach ($shapes as $shapeRecord) {
            $indexed[$shapeRecord->getAreaId()] = $shapeRecord;
        }

        return $indexed;
    }

    /**
     * Синхронизирует формы варианта.
     *
     * @param array<int, array<string, mixed>> $areas Области снимка.
     * @param int $snapshotVariantId Вариант снимка.
     * @param int $realVariantId Реальный вариант.
     * @param array<int, int> $areaMap Id снимка → реальный id области.
     * @param array<int, MapAreaShapeRecord> $existingByArea Известные формы.
     * @param array<int, true> $keptAreaIds Области, которые остаются.
     *
     * @return array<int, array<string, mixed>> Новые строки.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function syncShapes(
        array $areas,
        int $snapshotVariantId,
        int $realVariantId,
        array $areaMap,
        array $existingByArea,
        array &$keptAreaIds,
    ): array {
        $inserts = [];
        foreach ($areas as $area) {
            $inserts = [
                ...$inserts,
                ...$this->syncAreaShapes(
                    $area,
                    $snapshotVariantId,
                    $realVariantId,
                    $areaMap,
                    $existingByArea,
                    $keptAreaIds,
                ),
            ];
        }

        return $inserts;
    }

    /**
     * Синхронизирует формы одной области.
     *
     * @param array<string, mixed> $area Область снимка.
     * @param int $snapshotVariantId Вариант снимка.
     * @param int $realVariantId Реальный вариант.
     * @param array<int, int> $areaMap Id снимка → реальный id области.
     * @param array<int, MapAreaShapeRecord> $existingByArea Известные формы.
     * @param array<int, true> $keptAreaIds Области, которые остаются.
     *
     * @return array<int, array<string, mixed>> Новые строки.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function syncAreaShapes(
        array $area,
        int $snapshotVariantId,
        int $realVariantId,
        array $areaMap,
        array $existingByArea,
        array &$keptAreaIds,
    ): array {
        $inserts = [];
        foreach ($area['shapes'] as $shape) {
            if ($shape['variantId'] !== $snapshotVariantId) {
                continue;
            }

            $realAreaId = $this->mapped($areaMap, $area['id']);
            $keptAreaIds[$realAreaId] = true;
            $insert = $this->syncShape($shape, $realVariantId, $realAreaId, $existingByArea[$realAreaId] ?? null);
            if ($insert !== null) {
                $inserts[] = $insert;
            }
        }

        return $inserts;
    }

    /**
     * Применяет одну форму: вставляет, обновляет или оставляет как есть.
     *
     * @param array<string, mixed> $shape Форма снимка.
     * @param int $realVariantId Реальный вариант.
     * @param int $realAreaId Реальная область.
     * @param MapAreaShapeRecord|null $current Записанная форма или null.
     *
     * @return array<string, mixed>|null Строка вставки или null.
     */
    private function syncShape(array $shape, int $realVariantId, int $realAreaId, ?MapAreaShapeRecord $current): ?array
    {
        if ($current === null) {
            return [
                'variantId' => $realVariantId,
                'areaId' => $realAreaId,
                'path' => $shape['path'],
                'handles' => $shape['handles'],
                'isPresent' => $shape['isPresent'],
            ];
        }

        if ($this->sameShape($current, $shape)) {
            return null;
        }

        $this->areaStore->updateShape($current->getId(), $shape['path'], $shape['handles'], $shape['isPresent']);

        return null;
    }

    /**
     * Совпадает ли записанная форма с формой снимка.
     *
     * @param MapAreaShapeRecord $current Записанная форма.
     * @param array<string, mixed> $shape Форма снимка.
     *
     * @return bool Совпадают.
     */
    private function sameShape(MapAreaShapeRecord $current, array $shape): bool
    {
        return $current->getPath() === $shape['path']
            && $current->getHandles() === $shape['handles']
            && $current->isPresent() === $shape['isPresent'];
    }

    /**
     * Удаляет формы, которых нет в снимке.
     *
     * @param array<int, MapAreaShapeRecord> $existing Записанные формы.
     * @param array<int, true> $keptAreaIds Области, которые остаются.
     *
     * @return void
     */
    private function deleteAbsentShapes(array $existing, array $keptAreaIds): void
    {
        foreach ($existing as $shapeRecord) {
            if (!isset($keptAreaIds[$shapeRecord->getAreaId()])) {
                $this->areaStore->deleteShape($shapeRecord->getId());
            }
        }
    }

    /**
     * Вставляет новые формы пачками.
     *
     * @param array<int, array<string, mixed>> $inserts Новые строки.
     *
     * @return void
     */
    private function insertShapes(array $inserts): void
    {
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
