<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Repository;

use Closure;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Notes\Dto\NewNote;
use Mifrial\Messages\Notes\Dto\NoteFilter;
use Mifrial\Messages\Notes\Dto\NoteHostFilter;
use Mifrial\Messages\Notes\Dto\NoteHostGroup;
use Mifrial\Messages\Notes\Dto\NotePage;
use Mifrial\Messages\Notes\Dto\NoteRecord;
use Mifrial\Messages\Notes\Exception\NoteInvalidException;
use Mifrial\Messages\Notes\Exception\NoteNotFoundException;
use Mifrial\Messages\Notes\Service\NoteBodyText;

/**
 * Строки заметок: CRUD, страницы, агрегат сущностей и ручной порядок.
 */
final class NoteRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц для транзакций.
     * @param IOpenedRecords $noteRecords Строки `note`.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $gateway,
        private readonly IOpenedRecords $noteRecords,
    ) {
    }

    /**
     * Пишет заметку в конец списка автора и сущности.
     *
     * @param NewNote $note Поля.
     * @param int $authorId Автор.
     * @param DateTime $now Момент.
     *
     * @return NoteRecord Запись.
     *
     * @throws NoteInvalidException Если значения недопустимы.
     */
    public function add(NewNote $note, int $authorId, DateTime $now): NoteRecord
    {
        return $this->write(fn (): NoteRecord => $this->gateway->transaction(function () use (
            $note,
            $authorId,
            $now,
        ): NoteRecord {
            $sortOrder = $this->nextSortOrder($authorId, $note->getHostType(), $note->getHostId());
            $noteId = $this->noteRecords->add([
                'host_type' => $note->getHostType(),
                'host_id' => $note->getHostId(),
                'author_id' => $authorId,
                'title' => $note->getTitle(),
                'body_html' => $note->getBodyHtml(),
                'body_text' => NoteBodyText::derive($note->getBodyHtml()),
                'pinned' => false,
                'sort_order' => $sortOrder,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->getByIdOrFail($noteId);
        }));
    }

    /**
     * Обновляет переданные поля.
     *
     * @param NoteRecord $record Текущая строка.
     * @param array<string, mixed> $values Поля к записи.
     * @param DateTime $now Момент.
     *
     * @return NoteRecord После записи.
     *
     * @throws NoteInvalidException Если значения недопустимы.
     */
    public function update(NoteRecord $record, array $values, DateTime $now): NoteRecord
    {
        return $this->write(function () use ($record, $values, $now): NoteRecord {
            $this->noteRecords->update($record->getId(), [...$values, 'updated_at' => $now]);

            return $this->getByIdOrFail($record->getId());
        });
    }

    /**
     * Удаляет строку.
     *
     * @param int $noteId Идентификатор.
     *
     * @return void
     *
     * @throws NoteNotFoundException Если строки нет.
     */
    public function delete(int $noteId): void
    {
        $this->write(function () use ($noteId): void {
            $this->noteRecords->delete($noteId);
        });
    }

    /**
     * Строка по id.
     *
     * @param int $noteId Идентификатор.
     *
     * @return NoteRecord|null Запись или null.
     *
     * @throws NoteInvalidException Если строка битая.
     */
    public function getById(int $noteId): ?NoteRecord
    {
        $row = $this->noteRecords->getById($noteId);

        return $row === null ? null : NoteRecord::fromNormalized($row);
    }

    /**
     * Страница заметок.
     *
     * @param NoteFilter $filter Фильтр.
     *
     * @return NotePage Страница.
     *
     * @throws NoteInvalidException Если запрос недопустим.
     */
    public function findPage(NoteFilter $filter): NotePage
    {
        try {
            $sort = $filter->getHostId() === null
                ? ['pinned' => 'DESC', 'updated_at' => 'DESC', 'id' => 'DESC']
                : ['pinned' => 'DESC', 'sort_order' => 'ASC', 'id' => 'ASC'];
            $listResult = $this->noteRecords->getList(ListQuery::fromOptions([
                'filter' => $this->pageFilterTree($filter),
                'sort' => $sort,
                'limit' => $filter->getLimit(),
                'offset' => $filter->getOffset(),
                'countTotal' => true,
            ]));
        } catch (MapInvalidException $exception) {
            throw new NoteInvalidException('Note page query is invalid', $exception);
        }

        $items = [];
        foreach ($listResult->rows() as $row) {
            $items[] = NoteRecord::fromNormalized($row);
        }

        return new NotePage($items, $listResult->total() ?? 0);
    }

    /**
     * Сущности с заметками для левой колонки.
     *
     * @param NoteHostFilter $filter Фильтр.
     *
     * @return array<int, NoteHostGroup> Группы по свежести.
     *
     * @throws NoteInvalidException Если запрос недопустим.
     */
    public function findHosts(NoteHostFilter $filter): array
    {
        try {
            $rows = $this->noteRecords->aggregate(AggregateQuery::fromOptions([
                'filter' => $this->hostFilterTree($filter),
                'group' => ['host_type', 'host_id'],
                'select' => [
                    'host_type',
                    'host_id',
                    new CountField('note_count'),
                    new MaxField('updated_at', 'last_updated_at'),
                ],
                'limit' => 500,
            ]))->rows();
        } catch (MapInvalidException $exception) {
            throw new NoteInvalidException('Note host query is invalid', $exception);
        }

        $groups = [];
        foreach ($rows as $row) {
            $lastUpdatedAt = $row['last_updated_at'] ?? null;
            if (!$lastUpdatedAt instanceof DateTime) {
                throw new NoteInvalidException('Note host datetime is invalid');
            }

            $groups[] = new NoteHostGroup(
                (string) $row['host_type'],
                (int) $row['host_id'],
                (int) $row['note_count'],
                $lastUpdatedAt,
            );
        }

        usort(
            $groups,
            static fn (NoteHostGroup $left, NoteHostGroup $right): int => [
                $right->getLastUpdatedAt()->toUnix(),
                $right->getHostId(),
            ] <=> [
                $left->getLastUpdatedAt()->toUnix(),
                $left->getHostId(),
            ],
        );

        return array_slice($groups, 0, $filter->getLimit());
    }

    /**
     * Id активных заметок автора и сущности.
     *
     * @param int $authorId Автор.
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     *
     * @return array<int, int> Id в ручном порядке.
     *
     * @throws NoteInvalidException Если запрос недопустим.
     */
    public function idsForHost(int $authorId, string $hostType, int $hostId): array
    {
        try {
            $rows = $this->noteRecords->getList(ListQuery::fromOptions([
                'filter' => [
                    'author_id' => $authorId,
                    'host_type' => $hostType,
                    'host_id' => $hostId,
                    'active' => true,
                ],
                'sort' => ['sort_order' => 'ASC', 'id' => 'ASC'],
                'limit' => 10000,
                'select' => ['id'],
            ]))->rows();
        } catch (MapInvalidException $exception) {
            throw new NoteInvalidException('Note order query is invalid', $exception);
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * Пишет ручной порядок активных заметок.
     *
     * @param array<int, int> $noteIds Полный список id.
     * @param DateTime $now Момент.
     *
     * @return void
     *
     * @throws NoteInvalidException Если запись не удалась.
     */
    public function reorder(array $noteIds, DateTime $now): void
    {
        $this->write(fn (): mixed => $this->gateway->transaction(function () use ($noteIds, $now): void {
            foreach (array_values($noteIds) as $sortOrder => $noteId) {
                $this->noteRecords->update($noteId, ['sort_order' => $sortOrder, 'updated_at' => $now]);
            }
        }));
    }

    /**
     * Меняет закрепление.
     *
     * @param NoteRecord $record Текущая строка.
     * @param bool $pinned Новое значение.
     * @param DateTime $now Момент.
     *
     * @return NoteRecord После записи.
     *
     * @throws NoteInvalidException Если запись не удалась.
     */
    public function pin(NoteRecord $record, bool $pinned, DateTime $now): NoteRecord
    {
        return $this->update($record, ['pinned' => $pinned], $now);
    }

    /**
     * Гасит заметки удалённой или архивной сущности.
     *
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param DateTime $now Момент.
     *
     * @return int Число погашенных заметок.
     *
     * @throws NoteInvalidException Если запрос недопустим.
     */
    public function deactivateForHost(string $hostType, int $hostId, DateTime $now): int
    {
        try {
            $rows = $this->noteRecords->getList(ListQuery::fromOptions([
                'filter' => ['host_type' => $hostType, 'host_id' => $hostId, 'active' => true],
                'limit' => 10000,
                'select' => ['id'],
            ]))->rows();
        } catch (MapInvalidException $exception) {
            throw new NoteInvalidException('Note deactivate query is invalid', $exception);
        }

        $this->write(fn (): mixed => $this->gateway->transaction(function () use ($rows, $now): void {
            foreach ($rows as $row) {
                $this->noteRecords->update((int) $row['id'], ['active' => false, 'updated_at' => $now]);
            }
        }));

        return count($rows);
    }

    /**
     * Следующий sort_order среди активных заметок пары.
     *
     * @param int $authorId Автор.
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     *
     * @return int Порядок.
     *
     * @throws NoteInvalidException Если запрос недопустим.
     */
    private function nextSortOrder(int $authorId, string $hostType, int $hostId): int
    {
        try {
            $row = $this->noteRecords->getFirst(ListQuery::fromOptions([
                'filter' => [
                    'author_id' => $authorId,
                    'host_type' => $hostType,
                    'host_id' => $hostId,
                    'active' => true,
                ],
                'sort' => ['sort_order' => 'DESC'],
                'limit' => 1,
            ]));
        } catch (MapInvalidException $exception) {
            throw new NoteInvalidException('Note order query is invalid', $exception);
        }

        return $row === null ? 0 : ((int) $row['sort_order'] + 1);
    }

    /**
     * Дерево фильтра страницы.
     *
     * @param NoteFilter $filter Фильтр.
     *
     * @return array<string|int, mixed>|null Дерево.
     */
    private function pageFilterTree(NoteFilter $filter): ?array
    {
        $clauses = [];
        if ($filter->getAuthorId() !== null) {
            $clauses[] = ['author_id' => $filter->getAuthorId()];
        }

        if ($filter->getHostType() !== null) {
            $clauses[] = ['host_type' => $filter->getHostType()];
        }

        if ($filter->getHostId() !== null) {
            $clauses[] = ['host_id' => $filter->getHostId()];
        }

        if ($filter->getPinned() !== null) {
            $clauses[] = ['pinned' => $filter->getPinned()];
        }

        $stateClause = $this->stateClause($filter->getState());
        if ($stateClause !== null) {
            $clauses[] = $stateClause;
        }

        $queryClause = $this->queryClause($filter->getQ());
        if ($queryClause !== null) {
            $clauses[] = $queryClause;
        }

        return $this->andClauses($clauses);
    }

    /**
     * Дерево фильтра сущностей.
     *
     * @param NoteHostFilter $filter Фильтр.
     *
     * @return array<string|int, mixed>|null Дерево.
     */
    private function hostFilterTree(NoteHostFilter $filter): ?array
    {
        $clauses = [];
        if ($filter->getAuthorId() !== null) {
            $clauses[] = ['author_id' => $filter->getAuthorId()];
        }

        if ($filter->getHostType() !== null) {
            $clauses[] = ['host_type' => $filter->getHostType()];
        }

        $stateClause = $this->stateClause($filter->getState());
        if ($stateClause !== null) {
            $clauses[] = $stateClause;
        }

        $queryClause = $this->queryClause($filter->getQ());
        if ($queryClause !== null) {
            $clauses[] = $queryClause;
        }

        return $this->andClauses($clauses);
    }

    /**
     * Условие state.
     *
     * @param string $state active/archived/all.
     *
     * @return array<string, bool>|null Условие.
     */
    private function stateClause(string $state): ?array
    {
        return match ($state) {
            'active' => ['active' => true],
            'archived' => ['active' => false],
            default => null,
        };
    }

    /**
     * OR LIKE по заголовку и телу.
     *
     * @param string|null $query Подстрока.
     *
     * @return array<string|int, mixed>|null Условие.
     */
    private function queryClause(?string $query): ?array
    {
        if ($query === null) {
            return null;
        }

        return ['LOGIC' => 'OR', ['%title' => '%' . $query . '%'], ['%body_text' => '%' . $query . '%']];
    }

    /**
     * Склеивает условия через AND.
     *
     * @param array<int, array<string|int, mixed>> $clauses Условия.
     *
     * @return array<string|int, mixed>|null Дерево.
     */
    private function andClauses(array $clauses): ?array
    {
        if ($clauses === []) {
            return null;
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        return ['LOGIC' => 'AND', ...$clauses];
    }

    /**
     * Строка по id или исключение.
     *
     * @param int $noteId Идентификатор.
     *
     * @return NoteRecord Запись.
     *
     * @throws NoteNotFoundException Если строки нет.
     */
    private function getByIdOrFail(int $noteId): NoteRecord
    {
        $record = $this->getById($noteId);
        if ($record === null) {
            throw new NoteNotFoundException();
        }

        return $record;
    }

    /**
     * Переводит ошибки SmartTable в доменные.
     *
     * @param Closure $work Работа.
     *
     * @return mixed Результат.
     *
     * @throws NoteInvalidException Если значение или карта недопустимы.
     * @throws NoteNotFoundException Если строки нет.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new NoteInvalidException('Note is duplicate', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new NoteInvalidException('Note reference is invalid', $exception);
        } catch (RowNotFoundException $exception) {
            throw new NoteNotFoundException($exception);
        } catch (FieldRequiredException $exception) {
            throw new NoteInvalidException('Note field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new NoteInvalidException('Note field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new NoteInvalidException('Note map is invalid', $exception);
        }
    }
}
