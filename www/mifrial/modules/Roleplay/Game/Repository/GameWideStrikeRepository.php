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
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTable;

/**
 * Строки широкого удара 1 → N.
 */
final class GameWideStrikeRepository
{
    private readonly IOpenedRecords $strikeRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->strikeRecords = $smartTableGateway->open(GameWideStrikeTable::class)->records();
    }

    /**
     * Открытый широкий удар боя или null.
     *
     * @param int $battleId Бой.
     *
     * @return array<string, mixed>|null Колонки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findOpen(int $battleId): ?array
    {
        foreach ($this->rows($battleId, 'battle_id') as $row) {
            if (($row['open'] ?? null) === true) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Вставляет открытый удар.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(array $fields): int
    {
        try {
            return $this->strikeRecords->add($fields);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike field is invalid', $exception);
        }
    }

    /**
     * Закрывает удар.
     *
     * @param int $strikeId Удар.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function close(int $strikeId): void
    {
        try {
            $this->strikeRecords->update($strikeId, ['open' => false]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game wide strike was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike field is invalid', $exception);
        }
    }

    /**
     * Id ударов сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findIdsBySession(int $sessionId): array
    {
        $ids = [];
        foreach ($this->rows($sessionId, 'session_id') as $row) {
            $ids[] = $this->rowId($row);
        }

        return $ids;
    }

    /**
     * Снимает удары сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return list<int> Id снятых ударов.
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteBySession(int $sessionId): array
    {
        $ids = [];
        foreach ($this->rows($sessionId, 'session_id') as $row) {
            $ids[] = $this->rowId($row);
        }

        foreach ($ids as $strikeId) {
            $this->delete($strikeId);
        }

        return $ids;
    }

    /**
     * Строки фильтра.
     *
     * @param int $value Значение.
     * @param string $field Колонка.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function rows(int $value, string $field): array
    {
        $result = $this->strikeRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition($field, '=', $value),
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
                throw new GameInvalidException('Game wide strike row is invalid');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Удаляет строку.
     *
     * @param int $strikeId Удар.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function delete(int $strikeId): void
    {
        try {
            $this->strikeRecords->delete($strikeId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game wide strike was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike field is invalid', $exception);
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
            throw new GameInvalidException('Game wide strike row is invalid');
        }

        return $row['id'];
    }
}
