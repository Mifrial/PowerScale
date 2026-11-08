<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapNodeRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Запись узлов: обновление, удаление и вставка.
 */
final class MapNodeWriter
{
    /**
     * Создаёт писатель.
     *
     * @param MapNodeStore $nodeStore Узлы.
     * @param MapSaveRows $rows Общие операции над строками снимка.
     *
     * @return void
     */
    public function __construct(
        private readonly MapNodeStore $nodeStore,
        private readonly MapSaveRows $rows,
    ) {
    }

    /**
     * Пишет узлы карты.
     *
     * @param int $mapId Карта.
     * @param array<int, array<string, mixed>> $nodes Узлы снимка.
     *
     * @return array<int, int> Id снимка → реальный id.
     *
     * @throws MapInvalidException Если узел снимка неизвестен.
     */
    public function write(int $mapId, array $nodes): array
    {
        $existing = $this->nodeStore->findNodes($mapId);
        $existingById = $this->index($existing);
        [$updates, $inserts] = $this->rows->split($nodes, $existingById, 'Map node is unknown');
        foreach ($updates as $id => $node) {
            $this->nodeStore->updateNode($id, $this->fields($node));
        }

        foreach ($this->rows->stale($existingById, $updates) as $id) {
            $this->nodeStore->deleteNode($id);
        }

        $idMap = $this->rows->ids($updates);
        foreach ($inserts as $node) {
            $idMap[$node['id']] = $this->insert($mapId, $node);
        }

        return $idMap;
    }

    /**
     * Существующие id.
     *
     * @param array<int, MapNodeRecord> $nodes Узлы.
     *
     * @return array<int, true> Id → true.
     */
    private function index(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $nodeRecord) {
            $ids[$nodeRecord->getId()] = true;
        }

        return $ids;
    }

    /**
     * Поля строки.
     *
     * @param array<string, mixed> $node Узел.
     *
     * @return array<string, mixed> Поля.
     */
    private function fields(array $node): array
    {
        return [
            'name' => $node['name'],
            'short_info' => $node['shortInfo'],
            'public_info' => $node['publicInfo'],
            'gm_info' => $node['gmInfo'],
            'linked_map_id' => $node['linkedMapId'],
            'icon' => $node['icon'],
        ];
    }

    /**
     * Вставляет узел.
     *
     * @param int $mapId Карта.
     * @param array<string, mixed> $node Узел.
     *
     * @return int Реальный id.
     */
    private function insert(int $mapId, array $node): int
    {
        return $this->nodeStore->addNode(
            $mapId,
            $node['name'],
            $node['shortInfo'],
            $node['publicInfo'],
            $node['gmInfo'],
            $node['linkedMapId'],
            $node['icon'],
        );
    }
}
