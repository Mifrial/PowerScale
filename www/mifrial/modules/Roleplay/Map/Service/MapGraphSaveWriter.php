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
            'color' => $area['color'],
            'speed' => $area['speed'],
            'label' => $area['label'],
            'node_id' => $area['nodeId'] === null ? null : $this->mapped($nodeMap, $area['nodeId']),
            'sort_order' => $area['sortOrder'],
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
            $fields['color'],
            $fields['speed'],
            $fields['label'],
            $fields['node_id'],
            $fields['sort_order'],
        );
    }

    /**
     * Существующие id.
     *
     * @param array<int, MapAreaRecord> $records Записи.
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
