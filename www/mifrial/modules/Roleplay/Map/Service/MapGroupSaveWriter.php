<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapAreaGroupRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Запись групп областей снимка карты.
 */
final class MapGroupSaveWriter
{
    /**
     * Создаёт писатель.
     *
     * @param MapAreaGroupStore $groupStore Группы.
     * @param MapSaveRows $rows Общие операции над строками снимка.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAreaGroupStore $groupStore,
        private readonly MapSaveRows $rows,
    ) {
    }

    /**
     * Пишет группы.
     *
     * @param int $mapId Карта.
     * @param array<int, array<string, mixed>> $groups Группы снимка.
     *
     * @return array<int, int> Id снимка → реальный id.
     *
     * @throws MapInvalidException Если группа снимка неизвестна.
     */
    public function write(int $mapId, array $groups): array
    {
        $existingById = $this->index($this->groupStore->findGroups($mapId));
        [$updates, $inserts] = $this->rows->split($groups, $existingById, 'Map area group is unknown');

        $this->rename($updates, $existingById);
        foreach ($this->rows->stale($existingById, $updates) as $id) {
            $this->groupStore->deleteGroup($id);
        }

        $idMap = $this->rows->ids($updates);
        foreach ($inserts as $group) {
            $idMap[$group['id']] = $this->groupStore->addGroup($mapId, $group['label']);
        }

        return $idMap;
    }

    /**
     * Существующие группы по id.
     *
     * @param array<int, MapAreaGroupRecord> $records Записи.
     *
     * @return array<int, MapAreaGroupRecord> Id → группа.
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
     * Обновляет изменившиеся подписи.
     *
     * @param array<int, array<string, mixed>> $updates Группы снимка.
     * @param array<int, MapAreaGroupRecord> $existingById Известные группы.
     *
     * @return void
     */
    private function rename(array $updates, array $existingById): void
    {
        foreach ($updates as $id => $group) {
            if ($existingById[$id]->getLabel() !== $group['label']) {
                $this->groupStore->updateGroup($id, $group['label']);
            }
        }
    }
}
