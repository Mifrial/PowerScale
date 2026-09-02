<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Repository;

use Closure;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\User\Dto\NewUser;
use Mifrial\Core\User\Dto\UserPatch;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;

/**
 * Коллекция учёток: SmartTable внутри, снаружи профиль и USER_*.
 */
final class UserRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $userRecords Строки `user`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $userRecords,
    ) {
    }

    /**
     * Возвращает учётку по id.
     *
     * @param int $userId Идентификатор.
     *
     * @return UserRecord Учётка.
     *
     * @throws UserNotFoundException Если строки нет.
     */
    public function getById(int $userId): UserRecord
    {
        $row = $this->userRecords->getById($userId);
        if ($row === null) {
            throw new UserNotFoundException();
        }

        return UserRecord::fromNormalized($row);
    }

    /**
     * Ищет учётку по login.
     *
     * @param string $login Уже нормализованный логин.
     *
     * @return UserRecord|null Учётка или null.
     */
    public function findByLogin(string $login): ?UserRecord
    {
        return $this->recordOrNull($this->userRecords->getUnique($this->uniqueQuery('login', $login)));
    }

    /**
     * Ищет учётку по email.
     *
     * @param string $email Уже нормализованная почта.
     *
     * @return UserRecord|null Учётка или null.
     */
    public function findByEmail(string $email): ?UserRecord
    {
        return $this->recordOrNull($this->userRecords->getUnique($this->uniqueQuery('email', $email)));
    }

    /**
     * Добавляет учётку.
     *
     * @param NewUser $newUser Профиль.
     *
     * @return int Новый id.
     *
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если unique.
     */
    public function add(NewUser $newUser): int
    {
        return $this->write(function () use ($newUser): int {
            return $this->userRecords->add($newUser->fields());
        });
    }

    /**
     * Обновляет присутствующие свойства профиля.
     *
     * @param int $userId Идентификатор.
     * @param UserPatch $userPatch Свойства профиля.
     *
     * @return void
     *
     * @throws UserNotFoundException Если строки нет.
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если unique.
     */
    public function update(int $userId, UserPatch $userPatch): void
    {
        $this->write(function () use ($userId, $userPatch): mixed {
            $this->userRecords->update($userId, $userPatch->fields());

            return null;
        });
    }

    /**
     * Запрос unique-поиска по одному полю.
     *
     * @param string $fieldName Поле профиля.
     * @param string $fieldValue Значение.
     *
     * @return ListQuery Запрос.
     */
    private function uniqueQuery(string $fieldName, string $fieldValue): ListQuery
    {
        return ListQuery::fromOptions([
            'filter' => [$fieldName => $fieldValue],
            'limit' => 1,
        ]);
    }

    /**
     * Собирает Record или null.
     *
     * @param array<string, mixed>|null $row Строка или null.
     *
     * @return UserRecord|null Учётка.
     */
    private function recordOrNull(?array $row): ?UserRecord
    {
        if ($row === null) {
            return null;
        }

        return UserRecord::fromNormalized($row);
    }

    /**
     * Выполняет запись и мапит ошибки строки в User.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат $work.
     *
     * @throws UserDuplicateException Если unique.
     * @throws UserInvalidException Если поле/карта.
     * @throws UserNotFoundException Если строки нет.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new UserDuplicateException($exception);
        } catch (RowNotFoundException $exception) {
            throw new UserNotFoundException($exception);
        } catch (FieldRequiredException $exception) {
            throw new UserInvalidException('User field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new UserInvalidException('User field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException('User map is invalid', $exception);
        }
    }
}
