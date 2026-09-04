<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\User\Dto\NewUser;
use Mifrial\Core\User\Dto\UserPatch;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Dto\UserRecordPage;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Repository\UserRepository;

/**
 * Сценарий учётки: без SmartTable и без имён колонок.
 */
final class UserAccounts implements IUserAccounts
{
    /**
     * Создаёт фасад.
     *
     * @param UserRepository $userRepository Хранение учётки.
     * @param UserInputNormalizer $inputNormalizer Разбор сырых полей.
     *
     * @return void
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserInputNormalizer $inputNormalizer = new UserInputNormalizer(),
    ) {
    }

    /**
     * Возвращает учётку по id.
     *
     * @param int $userId Идентификатор.
     *
     * @return UserRecord Учётка.
     */
    public function getById(int $userId): UserRecord
    {
        return $this->userRepository->getById($userId);
    }

    /**
     * Страница учёток: id asc, COUNT фильтра.
     *
     * @param int $limit Размер.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока.
     * @param bool|null $active Фильтр active.
     *
     * @return UserRecordPage Страница.
     *
     * @throws UserInvalidException Если страница недопустима.
     */
    public function findPage(int $limit, int $offset, ?string $searchQuery, ?bool $active): UserRecordPage
    {
        return $this->userRepository->findPage($limit, $offset, $searchQuery, $active);
    }

    /**
     * Учётки по списку id.
     *
     * @param array<int, int> $userIds Идентификаторы.
     *
     * @return array<int, UserRecord> Найденные.
     *
     * @throws UserInvalidException Если больше 500 id.
     */
    public function getByIds(array $userIds): array
    {
        return $this->userRepository->getByIds($userIds);
    }

    /**
     * Ищет учётку по login.
     *
     * @param string $login Логин; будет trim.
     *
     * @return UserRecord|null Учётка или null.
     *
     * @throws UserInvalidException Если login пуст.
     */
    public function findByLogin(string $login): ?UserRecord
    {
        $normalizedLogin = trim($login);
        if ($normalizedLogin === '') {
            throw new UserInvalidException('User login is empty');
        }

        return $this->userRepository->findByLogin($normalizedLogin);
    }

    /**
     * Ищет учётку по email.
     *
     * @param string $email Почта; будет trim.
     *
     * @return UserRecord|null Учётка или null.
     */
    public function findByEmail(string $email): ?UserRecord
    {
        $normalizedEmail = trim($email);
        if ($normalizedEmail === '') {
            return null;
        }

        return $this->userRepository->findByEmail($normalizedEmail);
    }

    /**
     * Создаёт учётку.
     *
     * @param NewUser $newUser Профиль.
     *
     * @return int Новый id.
     */
    public function add(NewUser $newUser): int
    {
        return $this->userRepository->add($newUser);
    }

    /**
     * Создаёт учётку из сырых полей.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return int Новый id.
     */
    public function addFromInput(array $values): int
    {
        return $this->add($this->inputNormalizer->newUser($values));
    }

    /**
     * Обновляет переданные свойства профиля.
     *
     * @param int $userId Идентификатор.
     * @param UserPatch $userPatch Присутствующие свойства.
     *
     * @return void
     */
    public function update(int $userId, UserPatch $userPatch): void
    {
        $this->userRepository->update($userId, $userPatch);
    }
}
