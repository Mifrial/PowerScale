<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh
// replaceMembership и LAST_BYPASS остаются на фасаде; отдельный тип дублировал бы инвариант.

namespace Mifrial\Core\User\Service;

use Mifrial\Core\User\Dto\GroupPatch;
use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Dto\GroupRecordPage;
use Mifrial\Core\User\Dto\MemberIdPage;
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
 * Public-методы findByName (unique подпись) и getAssignOnRegisterIds нужны соседям;
 * отдельный фасад раздувал бы границу.
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
     * Группы по id; нет в БД — нет в результате.
     *
     * @param array<int, int> $groupIds Идентификаторы.
     *
     * @return array<int, GroupRecord> Ключ = id.
     */
    public function getByIds(array $groupIds): array
    {
        return $this->groupRepository->getByIds($groupIds);
    }

    /**
     * Страница групп: id asc, COUNT фильтра.
     *
     * @param int $limit Размер.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока.
     * @param bool|null $active Фильтр active.
     *
     * @return GroupRecordPage Страница.
     *
     * @throws UserInvalidException Если страница недопустима.
     */
    public function findPage(int $limit, int $offset, ?string $searchQuery, ?bool $active): GroupRecordPage
    {
        return $this->groupRepository->findPage($limit, $offset, $searchQuery, $active);
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
    public function getMemberIds(int $groupId): array
    {
        $this->groupRepository->getById($groupId);

        return $this->memberRepository->getUserIdsInGroup($groupId);
    }

    /**
     * Страница user_id группы: id членства asc, COUNT членств.
     *
     * @param int $groupId Группа.
     * @param int $limit Размер 1…500.
     * @param int $offset Сдвиг ≥ 0.
     *
     * @return MemberIdPage Страница.
     *
     * @throws UserNotFoundException Если группы нет.
     * @throws UserInvalidException Если страница недопустима.
     */
    public function findMemberPage(int $groupId, int $limit, int $offset): MemberIdPage
    {
        $this->groupRepository->getById($groupId);

        return $this->memberRepository->findUserIdPage($groupId, $limit, $offset);
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
        $this->deleteMembership($userId, $groupId, $this->groupRepository->getById($groupId));
    }

    /**
     * Ставит членство пользователя в точности как список id.
     *
     * @param int $userId Учётка.
     * @param array<int, int> $groupIds Id групп.
     *
     * @return void
     *
     * @throws UserNotFoundException Если нет учётки или группы.
     * @throws UserLastBypassException Если снять последнее живое bypass-членство.
     */
    public function replaceMembership(int $userId, array $groupIds): void
    {
        $this->userRepository->getById($userId);
        $wantedIds = $this->uniqueGroupIds($groupIds);
        $wantedGroups = $this->groupRepository->getByIds($wantedIds);
        foreach ($wantedIds as $groupId) {
            if (!isset($wantedGroups[$groupId])) {
                throw new UserNotFoundException();
            }
        }

        $currentIds = [];
        foreach ($this->memberRepository->getGroupIdsOfUser($userId) as $groupId) {
            $currentIds[$groupId] = $groupId;
        }

        foreach ($wantedIds as $groupId) {
            if (!isset($currentIds[$groupId])) {
                $this->memberRepository->add($userId, $groupId);
            }
        }

        $this->removeUnwantedMemberships($userId, $wantedIds, $currentIds);
    }

    /**
     * Id групп пользователя.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, int> group id.
     */
    public function getGroupIdsOfUser(int $userId): array
    {
        $this->userRepository->getById($userId);

        return $this->memberRepository->getGroupIdsOfUser($userId);
    }

    /**
     * Ключи прав активных групп без дубля.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, string> Ключи.
     *
     * @throws UserNotFoundException Если учётки нет или членство без группы.
     */
    public function getPermissionKeys(int $userId): array
    {
        $this->userRepository->getById($userId);
        $groupIds = $this->memberRepository->getGroupIdsOfUser($userId);
        $groupsById = $this->groupRepository->getByIds($groupIds);
        $permissionKeys = [];
        foreach ($groupIds as $groupId) {
            if (!isset($groupsById[$groupId])) {
                throw new UserNotFoundException();
            }

            $groupRecord = $groupsById[$groupId];
            if (!$groupRecord->isActive()) {
                continue;
            }

            foreach ($groupRecord->getPermissionKeys() as $permissionKey) {
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
     * Id групп с флагом автовыдачи.
     *
     * @return array<int, int> Id.
     */
    public function getAssignOnRegisterIds(): array
    {
        return $this->groupRepository->getAssignOnRegisterIds();
    }

    /**
     * Уникальные id без смены порядка первого вхождения.
     *
     * @param array<int, int> $groupIds Вход.
     *
     * @return array<int, int> Id.
     */
    private function uniqueGroupIds(array $groupIds): array
    {
        $uniqueIds = [];
        foreach ($groupIds as $groupId) {
            $uniqueIds[$groupId] = $groupId;
        }

        return array_values($uniqueIds);
    }

    /**
     * Снимает членства, которых нет в wanted.
     *
     * @param int $userId Учётка.
     * @param array<int, int> $wantedIds Новый набор.
     * @param array<int, int> $currentIds Текущие id, ключ = id.
     *
     * @return void
     *
     * @throws UserNotFoundException Если сняли id, которого нет в каталоге.
     * @throws UserLastBypassException Если снять последнее живое bypass-членство.
     */
    private function removeUnwantedMemberships(int $userId, array $wantedIds, array $currentIds): void
    {
        $wantedMap = [];
        foreach ($wantedIds as $groupId) {
            $wantedMap[$groupId] = $groupId;
        }

        $removedIds = [];
        foreach ($currentIds as $groupId) {
            if (!isset($wantedMap[$groupId])) {
                $removedIds[] = $groupId;
            }
        }

        $removedGroups = $this->groupRepository->getByIds($removedIds);
        foreach ($removedIds as $groupId) {
            if (!isset($removedGroups[$groupId])) {
                throw new UserNotFoundException();
            }

            $this->deleteMembership($userId, $groupId, $removedGroups[$groupId]);
        }
    }

    /**
     * Удаляет строку членства с инвариантом LAST_BYPASS.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     * @param GroupRecord $groupRecord Уже загруженная группа.
     *
     * @return void
     *
     * @throws UserNotFoundException Если членства нет.
     * @throws UserLastBypassException Если это последнее активное bypass-членство.
     */
    private function deleteMembership(int $userId, int $groupId, GroupRecord $groupRecord): void
    {
        $memberId = $this->memberRepository->findId($userId, $groupId);
        if ($memberId === null) {
            throw new UserNotFoundException();
        }

        if ($groupRecord->isActive() && $groupRecord->isBypass()) {
            $this->assertNotSoleActiveBypassMembership();
        }

        $this->memberRepository->deleteById($memberId);
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
            $this->assertGroupNotLastLiveBypass($current->getId());
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

        if (!$current->isActive() || !$current->isBypass()) {
            return false;
        }

        $nextActive = array_key_exists('active', $patchFields) ? $patchFields['active'] : $current->isActive();
        $nextBypass = array_key_exists('bypass', $patchFields) ? $patchFields['bypass'] : $current->isBypass();

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
