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
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;

/**
 * Членство: member_key и списки id, без публичного порта.
 */
final class UserGroupMemberRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $memberRecords Строки `user_group_member`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $memberRecords,
    ) {
    }

    /**
     * Добавляет членство.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     *
     * @return int Id строки членства.
     *
     * @throws UserDuplicateException Если пара уже есть.
     * @throws UserInvalidException Если значения недопустимы.
     */
    public function add(int $userId, int $groupId): int
    {
        return $this->write(function () use ($userId, $groupId): int {
            return $this->memberRecords->add([
                'user_id' => $userId,
                'group_id' => $groupId,
                'member_key' => $this->memberKey($userId, $groupId),
            ]);
        });
    }

    /**
     * Удаляет строку членства по id.
     *
     * @param int $memberId Идентификатор строки.
     *
     * @return void
     *
     * @throws UserNotFoundException Если строки нет.
     */
    public function deleteById(int $memberId): void
    {
        $this->write(function () use ($memberId): mixed {
            $this->memberRecords->delete($memberId);

            return null;
        });
    }

    /**
     * Ищет id строки членства.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     *
     * @return int|null Id или null.
     */
    public function findId(int $userId, int $groupId): ?int
    {
        $row = $this->memberRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['member_key' => $this->memberKey($userId, $groupId)],
            'limit' => 1,
            'select' => ['id'],
        ]));
        if ($row === null) {
            return null;
        }

        return (int) $row['id'];
    }

    /**
     * Id пользователей в группе (до 500).
     *
     * @param int $groupId Группа.
     *
     * @return array<int, int> user id.
     */
    public function listUserIdsInGroup(int $groupId): array
    {
        return $this->intColumn($this->pageByField('group_id', $groupId, 'user_id'), 'user_id');
    }

    /**
     * Id групп пользователя (до 500).
     *
     * @param int $userId Учётка.
     *
     * @return array<int, int> group id.
     */
    public function listGroupIdsOfUser(int $userId): array
    {
        return $this->intColumn($this->pageByField('user_id', $userId, 'group_id'), 'group_id');
    }

    /**
     * Число членств в указанных группах (COUNT, не размер страницы).
     *
     * @param array<int, int> $groupIds Группы.
     *
     * @return int Число строк.
     */
    public function countInGroups(array $groupIds): int
    {
        if ($groupIds === []) {
            return 0;
        }

        $listResult = $this->memberRecords->getList(ListQuery::fromOptions([
            'filter' => ['group_id' => $groupIds],
            'limit' => 1,
            'countTotal' => true,
            'select' => ['id'],
        ]));

        return (int) $listResult->total();
    }

    /**
     * Число членств в активных bypass-группах.
     *
     * @return int COUNT.
     */
    public function countActiveBypassMemberships(): int
    {
        return (int) $this->memberRecords->getList(ListQuery::fromOptions([
            'filter' => $this->activeBypassFilter(),
            'limit' => 1,
            'countTotal' => true,
            'select' => ['id'],
        ]))->total();
    }

    /**
     * Есть ли у учётки членство в активной bypass-группе.
     *
     * @param int $userId Учётка.
     *
     * @return bool true, если есть.
     */
    public function userHasActiveBypass(int $userId): bool
    {
        return $this->memberRecords->getFirst(ListQuery::fromOptions([
            'filter' => $this->activeBypassFilter(['user_id' => $userId]),
            'limit' => 1,
            'select' => ['id'],
        ])) !== null;
    }

    /**
     * Filter живого bypass на группе членства.
     *
     * @param array<string, mixed> $extra Свои условия.
     *
     * @return array<string, mixed> Filter getList.
     */
    private function activeBypassFilter(array $extra = []): array
    {
        return $extra + [
            'group_id.active' => true,
            'group_id.bypass' => true,
        ];
    }

    /**
     * Ключ уникальности пары.
     *
     * @param int $userId Учётка.
     * @param int $groupId Группа.
     *
     * @return string Ключ.
     */
    private function memberKey(int $userId, int $groupId): string
    {
        return $userId . ':' . $groupId;
    }

    /**
     * Страница членств по одному полю.
     *
     * @param string $filterName Поле фильтра.
     * @param int $filterValue Значение.
     * @param string $selectName Колонка в select.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function pageByField(string $filterName, int $filterValue, string $selectName): array
    {
        return $this->memberRecords->getList(ListQuery::fromOptions([
            'filter' => [$filterName => $filterValue],
            'limit' => 500,
            'select' => [$selectName],
        ]))->rows();
    }

    /**
     * Собирает int-колонку.
     *
     * @param array<int, array<string, mixed>> $rows Строки.
     * @param string $columnName Имя.
     *
     * @return array<int, int> Значения.
     */
    private function intColumn(array $rows, string $columnName): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = (int) $row[$columnName];
        }

        return $values;
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
            throw new UserInvalidException('Member field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new UserInvalidException('Member field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException('Member map is invalid', $exception);
        }
    }
}
