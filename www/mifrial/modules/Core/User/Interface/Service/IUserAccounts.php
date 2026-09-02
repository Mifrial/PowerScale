<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Interface\Service;

use Mifrial\Core\User\Dto\NewUser;
use Mifrial\Core\User\Dto\UserPatch;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;

/**
 * Фасад учётки для соседей: профиль, без схемы и без таблиц.
 */
interface IUserAccounts
{
    /**
     * Возвращает учётку по id.
     *
     * @param int $userId Идентификатор.
     *
     * @return UserRecord Учётка.
     *
     * @throws UserNotFoundException Если учётки нет.
     */
    public function getById(int $userId): UserRecord;

    /**
     * Ищет учётку по login.
     *
     * @param string $login Логин; будет trim.
     *
     * @return UserRecord|null Учётка или null.
     *
     * @throws UserInvalidException Если login пуст.
     */
    public function findByLogin(string $login): ?UserRecord;

    /**
     * Ищет учётку по email.
     *
     * @param string $email Почта; будет trim.
     *
     * @return UserRecord|null Учётка или null, если после trim пусто.
     */
    public function findByEmail(string $email): ?UserRecord;

    /**
     * Создаёт учётку.
     *
     * @param NewUser $newUser Профиль.
     *
     * @return int Новый id.
     *
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если login или email заняты.
     */
    public function add(NewUser $newUser): int;

    /**
     * Создаёт учётку из сырых полей (та же нормализация, что у профиля).
     *
     * @param array<string, mixed> $values Login, name, необязательный email и пр.
     *
     * @return int Новый id.
     *
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если login или email заняты.
     */
    public function addFromInput(array $values): int;

    /**
     * Обновляет переданные свойства профиля.
     *
     * @param int $userId Идентификатор.
     * @param UserPatch $userPatch Присутствующие свойства.
     *
     * @return void
     *
     * @throws UserNotFoundException Если учётки нет.
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если login или email заняты.
     */
    public function update(int $userId, UserPatch $userPatch): void;
}
