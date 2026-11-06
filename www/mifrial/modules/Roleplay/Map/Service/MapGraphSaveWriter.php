<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Запись областей и рёбер снимка карты.
 */
final class MapGraphSaveWriter
{
    /**
     * Создаёт писатель.
     *
     * @param MapAreaStore $areaStore Области и формы.
     * @param MapEdgeStore $edgeStore Рёбра.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAreaStore $areaStore,
        private readonly MapEdgeStore $edgeStore,
    ) {
    }

    /**
     * Пишет области.
     *
     * @param int $mapId Карта.
     * @param array<int, array<string, mixed>> $areas Области снимка.
     * @param array<int, int> $nodeMap Id снимка → реальный id узла.
     *
     * @return array<int, int> Id снимка → реальный id области.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    public function areas(int $mapId, array $areas, array $nodeMap): array
    {
        $existing = $this->areaStore->findAreas($mapId);
        $existingById = $this->index($existing);
        [$updates, $inserts] = $this->splitAreas($areas, $existingById);
        foreach ($updates as $id => $area) {
            $this->areaStore->updateArea($id, $this->areaFields($area, $nodeMap));
        }

        foreach ($existing as $areaRecord) {
            if (!isset($updates[$areaRecord->getId()])) {
                $this->areaStore->deleteArea($areaRecord->getId());
            }
        }

        $idMap = [];
        foreach ($updates as $id => $area) {
            $idMap[$id] = $id;
        }

        foreach ($inserts as $area) {
            $idMap[$area['id']] = $this->insertArea($mapId, $area, $nodeMap);
        }

        return $idMap;
    }

    /**
     * Пишет рёбра по вариантам.
     *
     * @param array<int, int> $variantMap Id снимка → реальный id варианта.
     * @param array<int, array<string, mixed>> $edges Рёбра снимка.
     * @param array<int, int> $nodeMap Id снимка → реальный id узла.
     *
     * @return void
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    public function edges(array $variantMap, array $edges, array $nodeMap): void
    {
        $byVariant = [];
        foreach ($edges as $edge) {
            $byVariant[$this->mapped($variantMap, $edge['variantId'])][] = $edge;
        }

        foreach ($variantMap as $realVariantId) {
            $this->writeEdgesOfVariant($realVariantId, $byVariant[$realVariantId] ?? [], $nodeMap);
        }
    }

    /**
     * Делит области на обновления и вставки.
     *
     * @param array<int, array<string, mixed>> $areas Области.
     * @param array<int, true> $existingById Известные id.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} Части.
     *
     * @throws MapInvalidException Если область снимка неизвестна.
     */
    private function splitAreas(array $areas, array $existingById): array
    {
        $updates = [];
        $inserts = [];
        foreach ($areas as $area) {
            $id = $area['id'];
            if ($id < 0) {
                $inserts[] = $area;

                continue;
            }

            if (!isset($existingById[$id])) {
                throw new MapInvalidException('Map area is unknown');
            }

            $updates[$id] = $area;
        }

        return [$updates, $inserts];
    }

    /**
     * Рёбра одного варианта.
     *
     * @param int $variantId Реальный вариант.
     * @param array<int, array<string, mixed>> $payload Рёбра.
     * @param array<int, int> $nodeMap Узлы.
     *
     * @return void
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function writeEdgesOfVariant(int $variantId, array $payload, array $nodeMap): void
    {
        $existing = $this->edgeStore->findEdges($variantId);
        $existingById = $this->index($existing);
        $kept = [];
        foreach ($payload as $edge) {
            $kept[$this->saveEdge($variantId, $edge, $nodeMap, $existingById)] = true;
        }

        foreach ($existing as $edgeRecord) {
            if (!isset($kept[$edgeRecord->getId()])) {
                $this->edgeStore->deleteEdge($edgeRecord->getId());
            }
        }
    }

    /**
     * Одно ребро.
     *
     * @param int $variantId Вариант.
     * @param array<string, mixed> $edge Ребро.
     * @param array<int, int> $nodeMap Узлы.
     * @param array<int, true> $existingById Известные id.
     *
     * @return int Реальный id.
     *
     * @throws MapInvalidException Если ребро неизвестно или ссылка не разрешилась.
     */
    private function saveEdge(int $variantId, array $edge, array $nodeMap, array $existingById): int
    {
        $fields = $this->edgeFields($variantId, $edge, $nodeMap);
        if ($edge['id'] > 0) {
            if (!isset($existingById[$edge['id']])) {
                throw new MapInvalidException('Map edge is unknown');
            }

            $this->edgeStore->updateEdge($edge['id'], $fields);

            return $edge['id'];
        }

        return $this->edgeStore->addEdge(
            $variantId,
            $fields['from_node_id'],
            $fields['to_node_id'],
            $fields['terrain_id'],
            $fields['path'],
            $fields['distance_meters'],
            $fields['is_directed'],
            $fields['label'],
        );
    }

    /**
     * Поля ребра.
     *
     * @param int $variantId Вариант.
     * @param array<string, mixed> $edge Ребро.
     * @param array<int, int> $nodeMap Узлы.
     *
     * @return array<string, mixed> Поля.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function edgeFields(int $variantId, array $edge, array $nodeMap): array
    {
        return [
            'variant_id' => $variantId,
            'from_node_id' => $this->mapped($nodeMap, $edge['fromNodeId']),
            'to_node_id' => $this->mapped($nodeMap, $edge['toNodeId']),
            'terrain_id' => $edge['terrainId'],
            'path' => $edge['path'],
            'distance_meters' => $edge['distanceMeters'],
            'is_directed' => $edge['isDirected'],
            'label' => $edge['label'],
        ];
    }

    /**
     * Поля области.
     *
     * @param array<string, mixed> $area Область.
     * @param array<int, int> $nodeMap Узлы.
     *
     * @return array<string, mixed> Поля.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function areaFields(array $area, array $nodeMap): array
    {
        return [
            'layer' => $area['layer'],
            'terrain_id' => $area['terrainId'],
            'kind' => $area['kind'],
            'label' => $area['label'],
            'node_id' => $area['nodeId'] === null ? null : $this->mapped($nodeMap, $area['nodeId']),
            'blocks_movement' => $area['blocksMovement'],
        ];
    }

    /**
     * Вставляет область.
     *
     * @param int $mapId Карта.
     * @param array<string, mixed> $area Область.
     * @param array<int, int> $nodeMap Узлы.
     *
     * @return int Реальный id.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function insertArea(int $mapId, array $area, array $nodeMap): int
    {
        $fields = $this->areaFields($area, $nodeMap);

        return $this->areaStore->addArea(
            $mapId,
            $fields['layer'],
            $fields['terrain_id'],
            $fields['kind'],
            $fields['label'],
            $fields['node_id'],
            $fields['blocks_movement'],
        );
    }

    /**
     * Существующие id.
     *
     * @param array<int, MapAreaRecord|MapEdgeRecord> $records Записи.
     *
     * @return array<int, true> Id → true.
     */
    private function index(array $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[$record->getId()] = true;
        }

        return $ids;
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
