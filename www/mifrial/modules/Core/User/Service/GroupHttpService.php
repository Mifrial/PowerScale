<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\User\Dto\Action\CreateGroupInput;
use Mifrial\Core\User\Dto\Action\FindPageInput;
use Mifrial\Core\User\Dto\Action\GetGroupMembersInput;
use Mifrial\Core\User\Dto\Action\UpdateGroupInput;
use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;

/**
 * HTTP-сценарии групп: guard, фасад, JSON Group / GroupMember.
 */
final class GroupHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IUserGroups $userGroups Группы.
     * @param IUserAccounts $userAccounts Учётки.
     * @param UserGroupMemberRepository $memberRepository COUNT членств.
     * @param GroupInputNormalizer $groupNormalizer New/Patch.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IUserGroups $userGroups,
        private readonly IUserAccounts $userAccounts,
        private readonly UserGroupMemberRepository $memberRepository,
        private readonly GroupInputNormalizer $groupNormalizer = new GroupInputNormalizer(),
    ) {
    }

    /**
     * Страница групп.
     *
     * @param FindPageInput $input JSON findPage.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function findPage(FindPageInput $input): array
    {
        $this->userAccess->requireKey('user_group.view');
        $searchQuery = $input->q->isPresent() ? $input->q->getValue() : null;
        $active = $input->active->isPresent() ? $input->active->getValue() : null;
        $groupPage = $this->userGroups->findPage($input->limit, $input->offset, $searchQuery, $active);
        $groupRecords = $groupPage->getRecords();
        $groupIds = [];
        foreach ($groupRecords as $groupRecord) {
            $groupIds[] = $groupRecord->getId();
        }

        return [
            'items' => $this->assembleMany($groupRecords, $this->memberRepository->getCountsByGroupIds($groupIds)),
            'total' => $groupPage->getTotal(),
        ];
    }

    /**
     * Одна группа.
     *
     * @param int $groupId Id.
     *
     * @return array<string, mixed> Group.
     */
    public function get(int $groupId): array
    {
        $this->userAccess->requireKey('user_group.view');

        return $this->assembleById($groupId);
    }

    /**
     * Страница членов группы.
     *
     * @param GetGroupMembersInput $input JSON getMembers.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function getMembers(GetGroupMembersInput $input): array
    {
        $this->userAccess->requireKey('user_group.view');
        $memberPage = $this->userGroups->findMemberPage($input->groupId, $input->limit, $input->offset);
        $members = [];
        foreach ($this->userAccounts->getByIds($memberPage->getIds()) as $userRecord) {
            $members[] = $this->assembleMember($userRecord);
        }

        return [
            'items' => $members,
            'total' => $memberPage->getTotal(),
        ];
    }

    /**
     * Создаёт группу.
     *
     * @param CreateGroupInput $input JSON create.
     *
     * @return array<string, mixed> Group.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function create(CreateGroupInput $input): array
    {
        $requestActor = $this->userAccess->requireKey('user_group.create');
        $fields = [
            'name' => $input->name,
            'permissions' => $input->permissions,
        ];
        $this->putOptionalBool($fields, 'active', $input->active);
        $this->putOptionalBool($fields, 'assign_on_register', $input->assignOnRegister);
        $newGroup = $this->groupNormalizer->newGroup($fields);
        $this->assertPermissionSubset(
            $requestActor,
            $this->stringList($newGroup->fields()['permissions'] ?? []),
            [],
        );
        $groupId = $this->userGroups->add($newGroup);

        return $this->assembleById($groupId);
    }

    /**
     * Обновляет присутствующие поля.
     *
     * @param UpdateGroupInput $input JSON update.
     *
     * @return array<string, mixed> Group.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws UserInvalidException Если patch пуст.
     */
    public function update(UpdateGroupInput $input): array
    {
        $requestActor = $this->userAccess->requireKey('user_group.edit');
        $current = $this->userGroups->getById($input->id);
        $fields = [];
        if ($input->name->isPresent()) {
            $fields['name'] = $input->name->getValue();
        }

        if ($input->permissions->isPresent()) {
            $fields['permissions'] = $input->permissions->getValue();
        }

        $this->putOptionalBool($fields, 'active', $input->active);
        $this->putOptionalBool($fields, 'assign_on_register', $input->assignOnRegister);
        $groupPatch = $this->groupNormalizer->patch($fields);
        if ($input->permissions->isPresent()) {
            $this->assertPermissionSubset(
                $requestActor,
                $this->stringList($groupPatch->fields()['permissions'] ?? []),
                $current->getPermissionKeys(),
            );
        }

        $this->userGroups->update($input->id, $groupPatch);

        return $this->assembleById($input->id);
    }

    /**
     * Выключает группу.
     *
     * @param int $groupId Id.
     *
     * @return null Успех без data.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function deactivate(int $groupId): mixed
    {
        $this->userAccess->requireKey('user_group.deactivate');
        $this->userGroups->getById($groupId);
        $this->userGroups->update($groupId, $this->groupNormalizer->patch(['active' => false]));

        return null;
    }

    /**
     * JSON группы по id с COUNT.
     *
     * @param int $groupId Id.
     *
     * @return array<string, mixed> Group.
     */
    private function assembleById(int $groupId): array
    {
        $groupRecord = $this->userGroups->getById($groupId);
        $countsByGroupId = $this->memberRepository->getCountsByGroupIds([$groupId]);

        return $this->assembleGroup($groupRecord, $countsByGroupId[$groupId] ?? 0);
    }

    /**
     * JSON нескольких групп.
     *
     * @param array<int, GroupRecord> $groupRecords Группы.
     * @param array<int, int> $countsByGroupId COUNT.
     *
     * @return array<int, array<string, mixed>> Group[].
     */
    private function assembleMany(array $groupRecords, array $countsByGroupId): array
    {
        $views = [];
        foreach ($groupRecords as $groupRecord) {
            $views[] = $this->assembleGroup(
                $groupRecord,
                $countsByGroupId[$groupRecord->getId()] ?? 0,
            );
        }

        return $views;
    }

    /**
     * JSON одной группы.
     *
     * @param GroupRecord $groupRecord Группа.
     * @param int $memberCount Число членов.
     *
     * @return array<string, mixed> Group.
     */
    private function assembleGroup(GroupRecord $groupRecord, int $memberCount): array
    {
        $permissionKeys = $groupRecord->getPermissionKeys();
        sort($permissionKeys);

        return [
            'id' => $groupRecord->getId(),
            'name' => $groupRecord->getName(),
            'active' => $groupRecord->isActive(),
            'memberCount' => $memberCount,
            'permissions' => $permissionKeys,
            'createdAt' => $groupRecord->getCreatedAt()->toUnix(),
            'bypass' => $groupRecord->isBypass(),
            'assignOnRegister' => $groupRecord->isAssignOnRegister(),
        ];
    }

    /**
     * JSON члена.
     *
     * @param UserRecord $userRecord Учётка.
     *
     * @return array<string, mixed> GroupMember.
     */
    private function assembleMember(UserRecord $userRecord): array
    {
        return [
            'id' => $userRecord->getId(),
            'name' => $userRecord->getName(),
            'login' => $userRecord->getLogin(),
        ];
    }

    /**
     * Итоговый набор ⊆ ключи актора; снятые ключи тоже из набора актора.
     *
     * @param RequestActor $requestActor Актор.
     * @param array<int, string> $wantedKeys Новый набор.
     * @param array<int, string> $currentKeys Текущий набор.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED, если чужой ключ.
     */
    private function assertPermissionSubset(
        RequestActor $requestActor,
        array $wantedKeys,
        array $currentKeys,
    ): void {
        if ($requestActor->hasBypass()) {
            return;
        }

        $actorKeys = [];
        foreach ($requestActor->getPermissionKeys() as $permissionKey) {
            $actorKeys[$permissionKey] = $permissionKey;
        }

        $wantedMap = [];
        foreach ($wantedKeys as $permissionKey) {
            $this->denyUnlessActorHas($actorKeys, $permissionKey);
            $wantedMap[$permissionKey] = $permissionKey;
        }

        foreach ($currentKeys as $permissionKey) {
            if (!isset($wantedMap[$permissionKey])) {
                $this->denyUnlessActorHas($actorKeys, $permissionKey);
            }
        }
    }

    /**
     * Отказ, если ключа нет у актора.
     *
     * @param array<string, string> $actorKeys Набор актора.
     * @param string $permissionKey Ключ.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function denyUnlessActorHas(array $actorKeys, string $permissionKey): void
    {
        if (!isset($actorKeys[$permissionKey])) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Кладёт bool, если ключ был.
     *
     * @param array<string, mixed> $fields Карта.
     * @param string $fieldName Имя.
     * @param OptionalBool $field Значение.
     *
     * @return void
     */
    private function putOptionalBool(array &$fields, string $fieldName, OptionalBool $field): void
    {
        if ($field->isPresent()) {
            $fields[$fieldName] = $field->getValue();
        }
    }

    /**
     * Строки из списка прав.
     *
     * @param mixed $permissions Поле.
     *
     * @return array<int, string> Ключи.
     */
    private function stringList(mixed $permissions): array
    {
        if (!is_array($permissions)) {
            return [];
        }

        $permissionKeys = [];
        foreach ($permissions as $permissionKey) {
            if (is_string($permissionKey)) {
                $permissionKeys[] = $permissionKey;
            }
        }

        return $permissionKeys;
    }
}
