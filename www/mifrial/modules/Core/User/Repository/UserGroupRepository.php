<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh
// findPage рядом с unique-поиском — одна коллекция групп.

namespace Mifrial\Core\User\Repository;

use Closure;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
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
        $this->assertPageBounds($limit, $offset);
        $listResult = $this->readList(ListQuery::fromOptions([
            'limit' => $limit,
            'offset' => $offset,
            'countTotal' => true,
            'sort' => ['id' => 'asc'],
            'filter' => $this->pageFilter($searchQuery, $active, ['name']),
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

    /**
     * Границы страницы.
     *
     * @param int $limit Размер.
     * @param int $offset Сдвиг.
     *
     * @return void
     *
     * @throws UserInvalidException Если вне диапазона.
     */
    private function assertPageBounds(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 500 || $offset < 0) {
            throw new UserInvalidException('Group page bounds are invalid');
        }
    }

    /**
     * Фильтр страницы: active AND (OR LIKE).
     *
     * @param string|null $searchQuery Подстрока.
     * @param bool|null $active Active.
     * @param array<int, string> $likeFields Поля LIKE.
     *
     * @return array<string|int, mixed>|null Дерево или нет WHERE.
     *
     * @throws UserInvalidException Если q слишком длинный.
     */
    private function pageFilter(?string $searchQuery, ?bool $active, array $likeFields): ?array
    {
        $clauses = [];
        if ($active !== null) {
            $clauses[] = ['=active' => $active];
        }

        $likePattern = $this->likePattern($searchQuery);
        if ($likePattern !== null) {
            $clauses[] = $this->likeOrGroup($likeFields, $likePattern);
        }

        if ($clauses === []) {
            return null;
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        $andGroup = ['LOGIC' => 'AND'];
        foreach ($clauses as $clause) {
            $andGroup[] = $clause;
        }

        return $andGroup;
    }

    /**
     * OR LIKE по полям.
     *
     * @param array<int, string> $likeFields Поля.
     * @param string $likePattern Шаблон.
     *
     * @return array<string|int, mixed> Группа OR.
     */
    private function likeOrGroup(array $likeFields, string $likePattern): array
    {
        $orGroup = ['LOGIC' => 'OR'];
        foreach ($likeFields as $fieldName) {
            $orGroup[] = ['%' . $fieldName => $likePattern];
        }

        return $orGroup;
    }

    /**
     * Шаблон contains или null.
     *
     * @param string|null $searchQuery Вход.
     *
     * @return string|null %q%.
     *
     * @throws UserInvalidException Если длиннее 100.
     */
    private function likePattern(?string $searchQuery): ?string
    {
        if ($searchQuery === null) {
            return null;
        }

        $trimmedQuery = trim($searchQuery);
        if ($trimmedQuery === '') {
            return null;
        }

        if (strlen($trimmedQuery) > 100) {
            throw new UserInvalidException('Search query is too long');
        }

        return '%' . addcslashes($trimmedQuery, '%_\\') . '%';
    }

    /**
     * getList ST с мапом ошибок.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return ListResult Страница.
     *
     * @throws UserInvalidException Если запрос недопустим.
     */
    private function readList(ListQuery $listQuery): ListResult
    {
        try {
            return $this->groupRecords->getList($listQuery);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException('Group list query is invalid', $exception);
        }
    }
}
