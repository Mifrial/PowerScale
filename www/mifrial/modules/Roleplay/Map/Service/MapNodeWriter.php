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
     *
     * @return void
     */
    public function __construct(
        private readonly MapNodeStore $nodeStore,
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
        [$updates, $inserts] = $this->split($nodes, $this->index($existing));
        foreach ($updates as $id => $node) {
            $this->nodeStore->updateNode($id, $this->fields($node));
        }

        $this->deleteMissing($existing, $updates);

        $idMap = $this->ids($updates);
        foreach ($inserts as $node) {
            $idMap[$node['id']] = $this->insert($mapId, $node);
        }

        return $idMap;
    }

    /**
     * Делит снимок на обновления и вставки.
     *
     * @param array<int, array<string, mixed>> $nodes Узлы.
     * @param array<int, true> $existingById Известные id.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} Части.
     *
     * @throws MapInvalidException Если узел снимка неизвестен.
     */
    private function split(array $nodes, array $existingById): array
    {
        $updates = [];
        $inserts = [];
        foreach ($nodes as $node) {
            $id = $node['id'];
            if ($id < 0) {
                $inserts[] = $node;

                continue;
            }

            if (!isset($existingById[$id])) {
                throw new MapInvalidException('Map node is unknown');
            }

            $updates[$id] = $node;
        }

        return [$updates, $inserts];
    }

    /**
     * Удаляет узлы, которых нет в снимке.
     *
     * @param array<int, MapNodeRecord> $existing Существующие.
     * @param array<int, array<string, mixed>> $updates Обновляемые.
     *
     * @return void
     */
    private function deleteMissing(array $existing, array $updates): void
    {
        foreach ($existing as $nodeRecord) {
            if (!isset($updates[$nodeRecord->getId()])) {
                $this->nodeStore->deleteNode($nodeRecord->getId());
            }
        }
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
     * Карта id для обновлённых строк.
     *
     * @param array<int, array<string, mixed>> $updates Обновляемые.
     *
     * @return array<int, int> Id снимка → реальный id.
     */
    private function ids(array $updates): array
    {
        $idMap = [];
        foreach ($updates as $id => $node) {
            $idMap[$id] = $id;
        }

        return $idMap;
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
