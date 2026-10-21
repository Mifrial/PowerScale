<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IConditionalOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameActionTargetTable;

/**
 * Общие строки целей действия.
 */
final class GameActionTargetRepository
{
    private readonly IOpenedRecords $records;

    private readonly IConditionalOpenedRecords $conditionalRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $openedTable = $smartTableGateway->open(GameActionTargetTable::class);
        $this->records = $openedTable->records();
        $this->conditionalRecords = $openedTable->conditionalRecords();
    }

    /**
     * Цели действия по id.
     *
     * @param int $actionId Действие.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getList(int $actionId): array
    {
        $result = $this->records->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('action_id', '=', $actionId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $rows = [];
        foreach ($result->rows() as $row) {
            if (!is_array($row)) {
                throw new GameInvalidException('Game action target row is invalid');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Первая цель действия или null.
     *
     * @param int $actionId Действие.
     *
     * @return array<string, mixed>|null Строка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findByAction(int $actionId): ?array
    {
        return $this->getList($actionId)[0] ?? null;
    }

    /**
     * Строка цели по id без блокировки.
     *
     * @param int $targetId Цель.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getById(int $targetId): ?array
    {
        return $this->records->getById($targetId);
    }

    /**
     * Строка цели по id с блокировкой до конца транзакции.
     *
     * @param int $targetId Цель.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function lockById(int $targetId): ?array
    {
        return $this->conditionalRecords->getCurrentById($targetId);
    }

    /**
     * Вставляет цель.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если поле или цель повторена.
     */
    public function add(array $fields): int
    {
        try {
            return $this->records->add($fields);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException
            | ReferenceConstraintException
            | UniqueConstraintException $exception
        ) {
            throw new GameInvalidException('Game action target field is invalid', $exception);
        }
    }

    /**
     * Обновляет цель.
     *
     * @param int $targetId Цель.
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function update(int $targetId, array $fields): void
    {
        try {
            $this->records->update($targetId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game action target was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException
            | ReferenceConstraintException
            | UniqueConstraintException $exception
        ) {
            throw new GameInvalidException('Game action target field is invalid', $exception);
        }
    }

    /**
     * Снимает цели действий.
     *
     * @param array<int, int> $actionIds Действия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteByActions(array $actionIds): void
    {
        foreach ($actionIds as $actionId) {
            foreach ($this->getList($actionId) as $row) {
                $this->delete($this->rowId($row));
            }
        }
    }

    /**
     * Удаляет цель.
     *
     * @param int $targetId Цель.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function delete(int $targetId): void
    {
        try {
            $this->records->delete($targetId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game action target was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException
            | ReferenceConstraintException $exception
        ) {
            throw new GameInvalidException('Game action target field is invalid', $exception);
        }
    }

    /**
     * Id строки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id битый.
     */
    private function rowId(array $row): int
    {
        if (!is_int($row['id'] ?? null)) {
            throw new GameInvalidException('Game action target row is invalid');
        }

        return $row['id'];
    }
}
