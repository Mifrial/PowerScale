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
use Mifrial\Core\User\Dto\GroupPatch;
use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;

/**
 * Коллекция групп: SmartTable внутри, снаружи GroupRecord и USER_*.
 */
final class UserGroupRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $groupRecords Строки `user_group`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $groupRecords,
    ) {
    }

    /**
     * Возвращает группу по id.
     *
     * @param int $groupId Идентификатор.
     *
     * @return GroupRecord Группа.
     *
     * @throws UserNotFoundException Если строки нет.
     */
    public function getById(int $groupId): GroupRecord
    {
        $row = $this->groupRecords->getById($groupId);
        if ($row === null) {
            throw new UserNotFoundException();
        }

        return GroupRecord::fromNormalized($row);
    }

    /**
     * Добавляет группу.
     *
     * @param NewGroup $newGroup Группа.
     *
     * @return int Новый id.
     *
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если имя занято.
     */
    public function add(NewGroup $newGroup): int
    {
        return $this->write(function () use ($newGroup): int {
            return $this->groupRecords->add($newGroup->fields());
        });
    }

    /**
     * Обновляет присутствующие свойства группы.
     *
     * @param int $groupId Идентификатор.
     * @param GroupPatch $groupPatch Свойства.
     *
     * @return void
     *
     * @throws UserNotFoundException Если строки нет.
     * @throws UserInvalidException Если значения недопустимы.
     * @throws UserDuplicateException Если имя занято.
     */
    public function update(int $groupId, GroupPatch $groupPatch): void
    {
        $this->write(function () use ($groupId, $groupPatch): mixed {
            $this->groupRecords->update($groupId, $groupPatch->fields());

            return null;
        });
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
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return null;
        }

        return $this->recordOrNull($this->groupRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['name' => $normalizedName],
            'limit' => 1,
        ])));
    }

    /**
     * Id единственной группы с bypass, если есть.
     *
     * @return int|null Id или null.
     */
    public function findBypassGroupId(): ?int
    {
        $row = $this->groupRecords->getFirst(ListQuery::fromOptions([
            'filter' => ['bypass' => true],
            'limit' => 1,
            'select' => ['id'],
        ]));
        if ($row === null) {
            return null;
        }

        return (int) $row['id'];
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
            throw new UserInvalidException('Group field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new UserInvalidException('Group field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException('Group map is invalid', $exception);
        }
    }

    /**
     * Собирает Record или null.
     *
     * @param array<string, mixed>|null $row Строка или null.
     *
     * @return GroupRecord|null Группа.
     */
    private function recordOrNull(?array $row): ?GroupRecord
    {
        if ($row === null) {
            return null;
        }

        return GroupRecord::fromNormalized($row);
    }
}
