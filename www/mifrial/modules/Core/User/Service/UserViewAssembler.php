<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Interface\Service\IUserViews;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;

/**
 * JSON User: unix UTC, email null, bypass из живых групп.
 */
final class UserViewAssembler implements IUserViews
{
    /**
     * Создаёт сборщик.
     *
     * @param UserGroupRepository $groupRepository Группы.
     * @param UserGroupMemberRepository $memberRepository Членство.
     *
     * @return void
     */
    public function __construct(
        private readonly UserGroupRepository $groupRepository,
        private readonly UserGroupMemberRepository $memberRepository,
    ) {
    }

    /**
     * Собирает объект User.
     *
     * @param UserRecord $userRecord Профиль.
     * @param DateTime|null $lastLogin Момент last_used_at.
     *
     * @return array<string, mixed> JSON-поля.
     */
    public function assemble(UserRecord $userRecord, ?DateTime $lastLogin): array
    {
        $views = $this->assembleMany([$userRecord]);
        $view = $views[0];
        if ($lastLogin instanceof DateTime) {
            $view['lastLogin'] = $lastLogin->toUnix();
        }

        return $view;
    }

    /**
     * Собирает JSON нескольких учёток без lastLogin.
     *
     * @param array<int, UserRecord> $userRecords Профили.
     *
     * @return array<int, array<string, mixed>> User[].
     */
    public function assembleMany(array $userRecords): array
    {
        if ($userRecords === []) {
            return [];
        }

        $userIds = [];
        foreach ($userRecords as $userRecord) {
            $userIds[] = $userRecord->getId();
        }

        $groupIdsByUserId = $this->memberRepository->getGroupIdsByUserIds($userIds);
        $groupsById = $this->groupsById($groupIdsByUserId);
        $views = [];
        foreach ($userRecords as $userRecord) {
            $views[] = $this->assembleLoaded($userRecord, $groupIdsByUserId, $groupsById);
        }

        return $views;
    }

    /**
     * Грузит уникальные группы членства.
     *
     * @param array<int, array<int, int>> $groupIdsByUserId user => group id[].
     *
     * @return array<int, GroupRecord> Ключ = id.
     */
    private function groupsById(array $groupIdsByUserId): array
    {
        $groupIds = [];
        foreach ($groupIdsByUserId as $userGroupIds) {
            foreach ($userGroupIds as $groupId) {
                $groupIds[$groupId] = $groupId;
            }
        }

        return $this->groupRepository->getByIds(array_values($groupIds));
    }

    /**
     * JSON одной учётки из уже загруженных групп.
     *
     * @param UserRecord $userRecord Профиль.
     * @param array<int, array<int, int>> $groupIdsByUserId Членство.
     * @param array<int, GroupRecord> $groupsById Группы.
     *
     * @return array<string, mixed> JSON.
     */
    private function assembleLoaded(
        UserRecord $userRecord,
        array $groupIdsByUserId,
        array $groupsById,
    ): array {
        $userId = $userRecord->getId();
        $groupRecords = $this->groupsOfUser($groupIdsByUserId[$userId] ?? [], $groupsById);
        $email = $userRecord->getEmail();
        $view = [
            'id' => $userId,
            'name' => $userRecord->getName(),
            'login' => $userRecord->getLogin(),
            'email' => $email,
            'groups' => $this->groupIds($groupRecords),
            'registered' => $this->unixOrNull($userRecord->getRegisteredAt()),
            'active' => $userRecord->isActive(),
            'bypass' => $this->hasActiveBypass($groupRecords),
            'permissions' => $this->permissionKeys($groupRecords),
            'deactivatedUntil' => $this->unixOrNull($userRecord->getDeactivatedUntil()),
            'deactivateReason' => $userRecord->getDeactivateReason(),
        ];

        return $this->withOptionalProfile($view, $userRecord);
    }

    /**
     * Группы учётки в порядке членства.
     *
     * @param array<int, int> $groupIds Id.
     * @param array<int, GroupRecord> $groupsById Карта.
     *
     * @return array<int, GroupRecord> Группы.
     */
    private function groupsOfUser(array $groupIds, array $groupsById): array
    {
        $groupRecords = [];
        foreach ($groupIds as $groupId) {
            if (isset($groupsById[$groupId])) {
                $groupRecords[] = $groupsById[$groupId];
            }
        }

        return $groupRecords;
    }

    /**
     * Id групп.
     *
     * @param array<int, GroupRecord> $groupRecords Группы.
     *
     * @return array<int, int> Id.
     */
    private function groupIds(array $groupRecords): array
    {
        $groupIds = [];
        foreach ($groupRecords as $groupRecord) {
            $groupIds[] = $groupRecord->getId();
        }

        return $groupIds;
    }

    /**
     * Живой bypass среди групп.
     *
     * @param array<int, GroupRecord> $groupRecords Группы.
     *
     * @return bool true, если обход ACL.
     */
    private function hasActiveBypass(array $groupRecords): bool
    {
        foreach ($groupRecords as $groupRecord) {
            if ($groupRecord->isActive() && $groupRecord->isBypass()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ключи активных групп без дубля.
     *
     * @param array<int, GroupRecord> $groupRecords Группы.
     *
     * @return array<int, string> Ключи.
     */
    private function permissionKeys(array $groupRecords): array
    {
        $permissionKeys = [];
        foreach ($groupRecords as $groupRecord) {
            if (!$groupRecord->isActive()) {
                continue;
            }

            $this->collectPermissionKeys($permissionKeys, $groupRecord->getPermissionKeys());
        }

        $orderedKeys = array_values($permissionKeys);
        sort($orderedKeys);

        return $orderedKeys;
    }

    /**
     * Кладёт строковые ключи прав в набор.
     *
     * @param array<string, string> $permissionKeys Набор.
     * @param array<int, string> $permissions Ключи группы.
     *
     * @return void
     */
    private function collectPermissionKeys(array &$permissionKeys, array $permissions): void
    {
        foreach ($permissions as $permissionKey) {
            if ($permissionKey !== '') {
                $permissionKeys[$permissionKey] = $permissionKey;
            }
        }
    }

    /**
     * Добавляет необязательные поля профиля.
     *
     * @param array<string, mixed> $view База.
     * @param UserRecord $userRecord Профиль.
     *
     * @return array<string, mixed> JSON.
     */
    private function withOptionalProfile(array $view, UserRecord $userRecord): array
    {
        $surname = $userRecord->getSurname();
        if (is_string($surname) && $surname !== '') {
            $view['surname'] = $surname;
        }

        $nickname = $userRecord->getNickname();
        if (is_string($nickname) && $nickname !== '') {
            $view['nickname'] = $nickname;
        }

        return $view;
    }

    /**
     * Unix из DateTime или null.
     *
     * @param DateTime|null $moment Момент.
     *
     * @return int|null Секунды.
     */
    private function unixOrNull(?DateTime $moment): ?int
    {
        if ($moment instanceof DateTime) {
            return $moment->toUnix();
        }

        return null;
    }
}
