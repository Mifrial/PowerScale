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
use Mifrial\Core\User\Dto\GroupRecordPage;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Repository\Query\UserPageQuery;

/**
 * Коллекция групп: SmartTable внутри, снаружи GroupRecord и USER_*.
 */
final class UserGroupRepository
{
    private readonly UserPageQuery $pageQuery;

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
        $this->pageQuery = new UserPageQuery('Group');
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
     * Id групп с assign_on_register.
     *
     * @return array<int, int> Id, до 500.
     */
    public function getAssignOnRegisterIds(): array
    {
        $groupIds = [];
        foreach (
            $this->groupRecords->getList(ListQuery::fromOptions([
                'filter' => ['assign_on_register' => true],
                'limit' => 500,
                'select' => ['id'],
            ]))->rows() as $row
        ) {
            $groupIds[] = (int) $row['id'];
        }

        return $groupIds;
    }

    /**
     * Группы по id; пустой список без запроса.
     *
     * @param array<int, int> $groupIds Идентификаторы.
     *
     * @return array<int, GroupRecord> Ключ = id.
     */
    public function getByIds(array $groupIds): array
    {
        $uniqueIds = [];
        foreach ($groupIds as $groupId) {
            $uniqueIds[$groupId] = $groupId;
        }

        $orderedIds = array_values($uniqueIds);
        if ($orderedIds === []) {
            return [];
        }

        $foundById = [];
        foreach (
            $this->groupRecords->getList(ListQuery::fromOptions([
                'filter' => ['id' => $orderedIds],
                'limit' => 500,
            ]))->rows() as $row
        ) {
            $groupRecord = GroupRecord::fromNormalized($row);
            $foundById[$groupRecord->getId()] = $groupRecord;
        }

        return $foundById;
    }

    /**
     * Страница групп: id asc, COUNT фильтра.
     *
     * @param int $limit Размер 1…500.
     * @param int $offset Сдвиг ≥ 0.
     * @param string|null $searchQuery Подстрока или null.
     * @param bool|null $active Фильтр active или null.
     *
     * @return GroupRecordPage Страница.
     *
     * @throws UserInvalidException Если страница или фильтр недопустимы.
     */
    public function findPage(int $limit, int $offset, ?string $searchQuery, ?bool $active): GroupRecordPage
    {
        $this->pageQuery->assertBounds($limit, $offset);
        $listResult = $this->pageQuery->readList($this->groupRecords, ListQuery::fromOptions([
            'limit' => $limit,
            'offset' => $offset,
            'countTotal' => true,
            'sort' => ['id' => 'asc'],
            'filter' => $this->pageQuery->filter($searchQuery, $active, ['name']),
        ]));
        $groupRecords = [];
        foreach ($listResult->rows() as $row) {
            $groupRecords[] = GroupRecord::fromNormalized($row);
        }

        return new GroupRecordPage($groupRecords, $listResult->total() ?? 0);
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
