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
     *
     * @return void
     */
    public function __construct(
        private readonly MapAreaGroupStore $groupStore,
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
        [$updates, $inserts] = $this->splitGroups($groups, $existingById);

        $this->rename($updates, $existingById);
        $this->deleteMissing($existingById, $updates);

        $idMap = [];
        foreach (array_keys($updates) as $id) {
            $idMap[$id] = $id;
        }

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
     * Делит группы на обновления и вставки.
     *
     * @param array<int, array<string, mixed>> $groups Группы снимка.
     * @param array<int, MapAreaGroupRecord> $existingById Известные группы.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} Части.
     *
     * @throws MapInvalidException Если группа снимка неизвестна.
     */
    private function splitGroups(array $groups, array $existingById): array
    {
        $updates = [];
        $inserts = [];
        foreach ($groups as $group) {
            $id = $group['id'];
            if ($id < 0) {
                $inserts[] = $group;

                continue;
            }

            if (!isset($existingById[$id])) {
                throw new MapInvalidException('Map area group is unknown');
            }

            $updates[$id] = $group;
        }

        return [$updates, $inserts];
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

    /**
     * Удаляет группы, которых нет в снимке.
     *
     * @param array<int, MapAreaGroupRecord> $existingById Известные группы.
     * @param array<int, array<string, mixed>> $updates Группы снимка.
     *
     * @return void
     */
    private function deleteMissing(array $existingById, array $updates): void
    {
        foreach ($existingById as $id => $groupRecord) {
            if (!isset($updates[$id])) {
                $this->groupStore->deleteGroup($id);
            }
        }
    }
}
