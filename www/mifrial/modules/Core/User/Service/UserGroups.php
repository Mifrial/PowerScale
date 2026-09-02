<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

namespace Mifrial\Core\User\Service;

use Mifrial\Core\User\Dto\GroupPatch;
use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserLastBypassException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Repository\UserRepository;

/**
 * Сценарий групп: без SmartTable и без имён колонок.
 *
 * Одиннадцатый public метод findByName нужен Auth 1; отдельный фасад раздувал бы границу.
 */
final class UserGroups implements IUserGroups
{
    /**
     * Создаёт фасад.
     *
     * @param UserGroupRepository $groupRepository Группы.
     * @param UserGroupMemberRepository $memberRepository Членство.
     * @param UserRepository $userRepository Учётки.
     *
     * @return void
     */
    public function __construct(
        private readonly UserGroupRepository $groupRepository,
        private readonly UserGroupMemberRepository $memberRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Возвращает группу по id.
     *
     * @param int $groupId Идентификатор.
     *
     * @return GroupRecord Группа.
     */
    public function getById(int $groupId): GroupRecord
    {
        return $this->groupRepository->getById($groupId);
    }

    /**
     * Создаёт группу.
     *
     * @param NewGroup $newGroup Группа.
     *
     * @return int Новый id.
     *
     * @throws UserInvalidException Если bypass уже занят.
     */
    public function add(NewGroup $newGroup): int
    {
        $this->assertBypassSlotFree(null, $newGroup->fields());

        return $this->groupRepository->add($newGroup);
    }

    /**
     * Обновляет переданные свойства группы.
     *
     * @param int $groupId Идентификатор.
     * @param GroupPatch $groupPatch Присутствующие свойства.
     *
     * @return void
     *
     * @throws UserInvalidException Если bypass уже занят.
     * @throws UserLastBypassException Если снять bypass/active нельзя.
     */
    public function update(int $groupId, GroupPatch $groupPatch): void
    {
        $current = $this->groupRepository->getById($groupId);
        $this->assertBypassSlotFree($groupId, $groupPatch->fields());
        $this->assertBypassPatchAllowed($current, $groupPatch);
        $this->groupRepository->update($groupId, $groupPatch);
    }

    /**
     * Id пользователей в группе.
     *
     * @param int $groupId Группа.
     *
     * @return array<int, int> user id.
     */
    public function members(int $groupId): array
    {
        $this->groupRepository->getById($groupId);

        return $this->memberRepository->listUserIdsInGroup($groupId);
    }

    /**
     * Добавляет членство.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     *
     * @return void
     */
    public function addMember(int $userId, int $groupId): void
    {
        $this->userRepository->getById($userId);
        $this->groupRepository->getById($groupId);
        $this->memberRepository->add($userId, $groupId);
    }

    /**
     * Снимает членство.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     *
     * @return void
     *
     * @throws UserNotFoundException Если членства нет.
     * @throws UserLastBypassException Если это последнее активное bypass-членство.
     */
    public function removeMember(int $userId, int $groupId): void
    {
        $memberId = $this->memberRepository->findId($userId, $groupId);
        if ($memberId === null) {
            throw new UserNotFoundException();
        }

        $groupValues = $this->groupRepository->getById($groupId)->values();
        if ($groupValues['active'] === true && $groupValues['bypass'] === true) {
            $this->assertNotSoleActiveBypassMembership();
        }

        $this->memberRepository->deleteById($memberId);
    }

    /**
     * Id групп пользователя.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, int> group id.
     */
    public function groupsOfUser(int $userId): array
    {
        $this->userRepository->getById($userId);

        return $this->memberRepository->listGroupIdsOfUser($userId);
    }

    /**
     * Ключи прав активных групп без дубля.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, string> Ключи.
     */
    public function permissionKeys(int $userId): array
    {
        $this->userRepository->getById($userId);
        $permissionKeys = [];
        foreach ($this->memberRepository->listGroupIdsOfUser($userId) as $groupId) {
            $groupValues = $this->groupRepository->getById($groupId)->values();
            if ($groupValues['active'] !== true) {
                continue;
            }

            foreach ($groupValues['permissions'] as $permissionKey) {
                $permissionKeys[$permissionKey] = $permissionKey;
            }
        }

        return array_values($permissionKeys);
    }

    /**
     * Есть ли членство в активной bypass-группе.
     *
     * @param int $userId Учётка.
     *
     * @return bool true, если bypass действует.
     */
    public function hasBypass(int $userId): bool
    {
        $this->userRepository->getById($userId);

        return $this->memberRepository->userHasActiveBypass($userId);
    }

    /**
     * Ищет группу по имени.
     *
     * @param string $name Имя; будет trim.
     *
     * @return GroupRecord|null Группа или null.
     */
    public function findByName(string $name): ?GroupRecord
    {
        return $this->groupRepository->findByName($name);
    }

    /**
     * Не больше одной группы с bypass, в том числе неактивной.
     *
     * @param int|null $keepGroupId Эта группа может сохранить флаг.
     * @param array<string, mixed> $fields New или patch.
     *
     * @return void
     *
     * @throws UserInvalidException Если слот bypass занят другой группой.
     */
    private function assertBypassSlotFree(?int $keepGroupId, array $fields): void
    {
        if (($fields['bypass'] ?? false) !== true) {
            return;
        }

        $existingId = $this->groupRepository->findBypassGroupId();
        if ($existingId === null || $existingId === $keepGroupId) {
            return;
        }

        throw new UserInvalidException('Bypass group already exists');
    }

    /**
     * Отвергает patch, который выключил бы последний живой bypass.
     *
     * @param GroupRecord $current Текущая группа.
     * @param GroupPatch $groupPatch Patch.
     *
     * @return void
     *
     * @throws UserLastBypassException Если после patch не останется bypass-членств.
     */
    private function assertBypassPatchAllowed(GroupRecord $current, GroupPatch $groupPatch): void
    {
        if ($this->patchTurnsOffActiveBypass($current, $groupPatch)) {
            $this->assertGroupNotLastLiveBypass((int) $current->values()['id']);
        }
    }

    /**
     * Patch снимает active или bypass у текущей живой bypass-группы.
     *
     * @param GroupRecord $current Текущая группа.
     * @param GroupPatch $groupPatch Patch.
     *
     * @return bool true, если живой bypass гасится.
     */
    private function patchTurnsOffActiveBypass(GroupRecord $current, GroupPatch $groupPatch): bool
    {
        $patchFields = $groupPatch->fields();
        if (!array_key_exists('active', $patchFields) && !array_key_exists('bypass', $patchFields)) {
            return false;
        }

        $currentValues = $current->values();
        if ($currentValues['active'] !== true || $currentValues['bypass'] !== true) {
            return false;
        }

        $nextActive = array_key_exists('active', $patchFields) ? $patchFields['active'] : $currentValues['active'];
        $nextBypass = array_key_exists('bypass', $patchFields) ? $patchFields['bypass'] : $currentValues['bypass'];

        return $nextActive !== true || $nextBypass !== true;
    }

    /**
     * Отвергает выключение группы, если без её членов не останется bypass.
     *
     * @param int $groupId Группа.
     *
     * @return void
     *
     * @throws UserLastBypassException Если счётчик без этой группы ноль.
     */
    private function assertGroupNotLastLiveBypass(int $groupId): void
    {
        $total = $this->countActiveBypassMemberships();
        if ($total === 0) {
            return;
        }

        if ($total - $this->memberRepository->countInGroups([$groupId]) === 0) {
            throw new UserLastBypassException();
        }
    }

    /**
     * Отвергает снятие единственного активного bypass-членства.
     *
     * @return void
     *
     * @throws UserLastBypassException Если счётчик равен 1.
     */
    private function assertNotSoleActiveBypassMembership(): void
    {
        if ($this->countActiveBypassMemberships() === 1) {
            throw new UserLastBypassException();
        }
    }

    /**
     * Число членств в активных bypass-группах.
     *
     * @return int COUNT.
     */
    private function countActiveBypassMemberships(): int
    {
        return $this->memberRepository->countActiveBypassMemberships();
    }
}
