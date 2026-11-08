<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

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
        $existing = $this->groupStore->findGroups($mapId);
        $existingById = [];
        foreach ($existing as $groupRecord) {
            $existingById[$groupRecord->getId()] = $groupRecord;
        }

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

        foreach ($updates as $id => $group) {
            if ($existingById[$id]->getLabel() !== $group['label']) {
                $this->groupStore->updateGroup($id, $group['label']);
            }
        }

        foreach ($existingById as $id => $groupRecord) {
            if (!isset($updates[$id])) {
                $this->groupStore->deleteGroup($id);
            }
        }

        $idMap = [];
        foreach ($updates as $id => $group) {
            $idMap[$id] = $id;
        }

        foreach ($inserts as $group) {
            $idMap[$group['id']] = $this->groupStore->addGroup($mapId, $group['label']);
        }

        return $idMap;
    }
}
