<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Запись областей снимка карты.
 */
final class MapGraphSaveWriter
{
    /**
     * Сколько областей вставлять одним multi-insert.
     */
    private const INSERT_CHUNK = 500;

    /**
     * Создаёт писатель.
     *
     * @param MapAreaStore $areaStore Области и формы.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAreaStore $areaStore,
    ) {
    }

    /**
     * Пишет области.
     *
     * @param int $mapId Карта.
     * @param array<int, array<string, mixed>> $areas Области снимка.
     * @param array<int, int> $nodeMap Id снимка → реальный id узла.
     * @param array<int, int> $groupMap Id снимка → реальный id группы.
     *
     * @return array<int, int> Id снимка → реальный id области.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    public function areas(int $mapId, array $areas, array $nodeMap, array $groupMap): array
    {
        $existing = $this->areaStore->findAreas($mapId);
        $existingById = $this->index($existing);
        [$updates, $inserts] = $this->splitAreas($areas, $existingById);

        foreach ($updates as $id => $area) {
            $fields = $this->areaFields($area, $nodeMap, $groupMap);
            if ($this->isSameArea($existingById[$id], $fields)) {
                continue;
            }

            $this->areaStore->updateArea($id, $fields);
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

        $rows = [];
        foreach ($inserts as $area) {
            $fields = $this->areaFields($area, $nodeMap, $groupMap);
            $rows[] = [
                'layer' => $fields['layer'],
                'color' => $fields['color'],
                'stroke' => $fields['stroke'],
                'strokeWidth' => $fields['stroke_width'],
                'decor' => $fields['decor'],
                'speed' => $fields['speed'],
                'label' => $fields['label'],
                'nodeId' => $fields['node_id'],
                'sortOrder' => $fields['sort_order'],
                'groupId' => $fields['group_id'],
            ];
        }

        $offset = 0;
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            $insertedIds = $this->areaStore->addAreas($mapId, $chunk);
            foreach ($insertedIds as $index => $realId) {
                $idMap[$inserts[$offset + $index]['id']] = $realId;
            }

            $offset += count($insertedIds);
        }

        return $idMap;
    }

    /**
     * Совпадают ли поля области с уже записанной строкой.
     *
     * @param MapAreaRecord $areaRecord Текущая строка.
     * @param array<string, mixed> $fields Новые поля.
     *
     * @return bool Совпадают.
     */
    private function isSameArea(MapAreaRecord $areaRecord, array $fields): bool
    {
        return $areaRecord->getLayer() === $fields['layer']
            && $areaRecord->getColor() === $fields['color']
            && $areaRecord->getStroke() === $fields['stroke']
            && $areaRecord->getStrokeWidth() === $fields['stroke_width']
            && $this->sameDecor($areaRecord->getDecor(), $fields['decor'])
            && $areaRecord->getSpeed() === $fields['speed']
            && $areaRecord->getLabel() === $fields['label']
            && $areaRecord->getNodeId() === $fields['node_id']
            && $areaRecord->getGroupId() === $fields['group_id']
            && $areaRecord->getSortOrder() === $fields['sort_order'];
    }

    /**
     * Сравнивает декор без учёта порядка ключей.
     *
     * @param array<string, mixed> $left Текущий декор.
     * @param array<string, mixed> $right Новый декор.
     *
     * @return bool Совпадают.
     */
    private function sameDecor(array $left, array $right): bool
    {
        ksort($left);
        ksort($right);

        return $left === $right;
    }

    /**
     * Делит области на обновления и вставки.
     *
     * @param array<int, array<string, mixed>> $areas Области.
     * @param array<int, MapAreaRecord> $existingById Известные строки.
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
     * Поля области.
     *
     * @param array<string, mixed> $area Область.
     * @param array<int, int> $nodeMap Узлы.
     * @param array<int, int> $groupMap Группы.
     *
     * @return array<string, mixed> Поля.
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    private function areaFields(array $area, array $nodeMap, array $groupMap): array
    {
        return [
            'layer' => $area['layer'],
            'color' => $area['color'],
            'stroke' => $area['stroke'],
            'stroke_width' => $area['strokeWidth'],
            'decor' => $area['decor'],
            'speed' => $area['speed'],
            'label' => $area['label'],
            'node_id' => $area['nodeId'] === null ? null : $this->mapped($nodeMap, $area['nodeId']),
            'group_id' => $area['groupId'] === null ? null : $this->mapped($groupMap, $area['groupId']),
            'sort_order' => $area['sortOrder'],
        ];
    }

    /**
     * Существующие строки по id.
     *
     * @param array<int, MapAreaRecord> $records Записи.
     *
     * @return array<int, MapAreaRecord> Id → строка.
     */
    private function index(array $records): array
    {
        $indexed = [];
        foreach ($records as $record) {
            $indexed[$record->getId()] = $record;
        }

        return $indexed;
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
