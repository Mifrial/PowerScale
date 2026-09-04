<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * HTTP-замена членства: ACL bypass, запись — пакетом на фасаде.
 */
final class UserMembershipSync
{
    /**
     * Создаёт синхронизатор.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IUserGroups $userGroups Группы.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IUserGroups $userGroups,
    ) {
    }

    /**
     * Ставит членство в точности как список id.
     *
     * @param int $userId Учётка.
     * @param array<int, mixed> $groupIds Id групп.
     *
     * @return void
     *
     * @throws UserInvalidException Если id не int.
     * @throws UserNotFoundException Если группы нет.
     */
    public function replace(int $userId, array $groupIds): void
    {
        $wantedIds = $this->uniqueIntIds($this->intIdList($groupIds));
        $currentIds = $this->uniqueIntIds($this->userGroups->getGroupIdsOfUser($userId));
        $groupsById = $this->userGroups->getByIds(array_values($wantedIds + $currentIds));
        $this->assertReplaceAllowed($wantedIds, $currentIds, $groupsById);
        $this->userGroups->replaceMembership($userId, array_values($wantedIds));
    }

    /**
     * Все wanted есть; bypass на add и remove.
     *
     * @param array<int, int> $wantedIds Новый набор, ключ = id.
     * @param array<int, int> $currentIds Текущий набор, ключ = id.
     * @param array<int, GroupRecord> $groupsById Загруженные группы.
     *
     * @return void
     *
     * @throws UserNotFoundException Если группы нет.
     */
    private function assertReplaceAllowed(array $wantedIds, array $currentIds, array $groupsById): void
    {
        foreach ($wantedIds as $groupId) {
            $this->assertKnownGroup($groupsById, $groupId);
            $this->assertBypassOnRecord($groupsById[$groupId]);
        }

        foreach ($currentIds as $groupId) {
            if (!isset($wantedIds[$groupId])) {
                $this->assertKnownGroup($groupsById, $groupId);
                $this->assertBypassOnRecord($groupsById[$groupId]);
            }
        }
    }

    /**
     * Группа была в пачке.
     *
     * @param array<int, GroupRecord> $groupsById Карта.
     * @param int $groupId Id.
     *
     * @return void
     *
     * @throws UserNotFoundException Если нет.
     */
    private function assertKnownGroup(array $groupsById, int $groupId): void
    {
        if (!isset($groupsById[$groupId])) {
            throw new UserNotFoundException();
        }
    }

    /**
     * Эскалация по флагу bypass на уже загруженной группе.
     *
     * @param GroupRecord $groupRecord Группа.
     *
     * @return void
     */
    private function assertBypassOnRecord(GroupRecord $groupRecord): void
    {
        $this->userAccess->assertCanAssignBypassMembership($groupRecord->isBypass());
    }

    /**
     * Id как int[].
     *
     * @param array<int, mixed> $groupIds Вход.
     *
     * @return array<int, int> Id.
     *
     * @throws UserInvalidException Если не int.
     */
    private function intIdList(array $groupIds): array
    {
        $normalizedIds = [];
        foreach ($groupIds as $groupId) {
            if (!is_int($groupId)) {
                throw new UserInvalidException('User group id is invalid');
            }

            $normalizedIds[] = $groupId;
        }

        return $normalizedIds;
    }

    /**
     * Уникальные id, ключ = id.
     *
     * @param array<int, int> $groupIds Вход.
     *
     * @return array<int, int> Карта.
     */
    private function uniqueIntIds(array $groupIds): array
    {
        $uniqueIds = [];
        foreach ($groupIds as $groupId) {
            $uniqueIds[$groupId] = $groupId;
        }

        return $uniqueIds;
    }
}
