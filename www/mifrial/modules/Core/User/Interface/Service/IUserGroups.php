<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Interface\Service;

use Mifrial\Core\User\Dto\GroupPatch;
use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserLastBypassException;
use Mifrial\Core\User\Exception\UserNotFoundException;

/**
 * Фасад групп и членства для соседей: без схемы и без таблиц.
 */
interface IUserGroups
{
    /**
     * Возвращает группу по id.
     *
     * @param int $groupId Идентификатор.
     *
     * @return GroupRecord Группа.
     *
     * @throws UserNotFoundException Если группы нет.
     */
    public function getById(int $groupId): GroupRecord;

    /**
     * Создаёт группу.
     *
     * @param NewGroup $newGroup Группа.
     *
     * @return int Новый id.
     *
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если имя занято.
     */
    public function add(NewGroup $newGroup): int;

    /**
     * Обновляет переданные свойства группы.
     *
     * @param int $groupId Идентификатор.
     * @param GroupPatch $groupPatch Присутствующие свойства.
     *
     * @return void
     *
     * @throws UserNotFoundException Если группы нет.
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если имя занято.
     * @throws UserLastBypassException Если снять bypass/active нельзя.
     */
    public function update(int $groupId, GroupPatch $groupPatch): void;

    /**
     * Id пользователей в группе.
     *
     * @param int $groupId Группа.
     *
     * @return array<int, int> user id, до 500.
     *
     * @throws UserNotFoundException Если группы нет.
     */
    public function members(int $groupId): array;

    /**
     * Добавляет членство.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     *
     * @return void
     *
     * @throws UserNotFoundException Если нет учётки или группы.
     * @throws UserDuplicateException Если пара уже есть.
     */
    public function addMember(int $userId, int $groupId): void;

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
    public function removeMember(int $userId, int $groupId): void;

    /**
     * Id групп пользователя.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, int> group id, до 500.
     *
     * @throws UserNotFoundException Если учётки нет.
     */
    public function groupsOfUser(int $userId): array;

    /**
     * Ключи прав активных групп без дубля.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, string> Ключи.
     *
     * @throws UserNotFoundException Если учётки нет.
     */
    public function permissionKeys(int $userId): array;

    /**
     * Есть ли членство в активной bypass-группе.
     *
     * @param int $userId Учётка.
     *
     * @return bool true, если bypass действует.
     *
     * @throws UserNotFoundException Если учётки нет.
     */
    public function hasBypass(int $userId): bool;

    /**
     * Ищет группу по имени.
     *
     * @param string $name Имя; будет trim.
     *
     * @return GroupRecord|null Группа или null, если после trim пусто или нет строки.
     */
    public function findByName(string $name): ?GroupRecord;
}
