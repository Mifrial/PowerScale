<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapAreaGroupRecord;
use Mifrial\Roleplay\Map\Repository\MapAreaGroupRepository;

/**
 * Группы областей: один вход для редактора и снимка.
 */
final class MapAreaGroupStore
{
    /**
     * Создаёт стор.
     *
     * @param MapAreaGroupRepository $groupRepository Группы.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAreaGroupRepository $groupRepository,
    ) {
    }

    /**
     * Группы карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapAreaGroupRecord> Группы.
     */
    public function findGroups(int $mapId): array
    {
        return $this->groupRepository->findByMap($mapId);
    }

    /**
     * Добавляет группу.
     *
     * @param int $mapId Карта.
     * @param string $label Подпись.
     *
     * @return int Id.
     */
    public function addGroup(int $mapId, string $label): int
    {
        return $this->groupRepository->add($mapId, $label);
    }

    /**
     * Пишет подпись группы.
     *
     * @param int $groupId Группа.
     * @param string $label Подпись.
     *
     * @return void
     */
    public function updateGroup(int $groupId, string $label): void
    {
        $this->groupRepository->update($groupId, $label);
    }

    /**
     * Удаляет группу.
     *
     * @param int $groupId Группа.
     *
     * @return void
     */
    public function deleteGroup(int $groupId): void
    {
        $this->groupRepository->delete($groupId);
    }
}
