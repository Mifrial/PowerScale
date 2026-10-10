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
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameWideStrikeCommandTable;

/**
 * Журнал команд широкого удара.
 */
final class GameWideStrikeCommandRepository
{
    private readonly IOpenedRecords $commandRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->commandRecords = $smartTableGateway->open(GameWideStrikeCommandTable::class)->records();
    }

    /**
     * Строка ключа или null.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     *
     * @return array{id: int, body: array<string, mixed>, result: array<string, mixed>}|null Пара.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findByKey(int $gameId, string $idempotencyKey): ?array
    {
        $result = $this->commandRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('game_id', '=', $gameId),
                new FilterCondition('idempotency_key', '=', $idempotencyKey),
            ]),
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ));
        $rows = $result->rows();
        if ($rows === []) {
            return null;
        }

        return $this->pair($rows[0]);
    }

    /**
     * Резервирует ключ до записи итогового результата.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return int Id reservation.
     *
     * @throws GameBattleConflictException Если ключ уже есть.
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function reserve(
        int $gameId,
        int $sessionId,
        string $idempotencyKey,
        array $body,
    ): int {
        try {
            return $this->commandRecords->add([
                'game_id' => $gameId,
                'session_id' => $sessionId,
                'idempotency_key' => $idempotencyKey,
                'body' => $body,
                'result' => [],
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new GameBattleConflictException(null, 'Game wide strike key is already used', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike command field is invalid', $exception);
        }
    }

    /**
     * Завершает reservation сохранённым итогом.
     *
     * @param int $commandId Id reservation.
     * @param array<string, mixed> $result Итог команды.
     *
     * @return void
     *
     * @throws GameNotFoundException Если reservation отсутствует.
     * @throws GameInvalidException Если поле результата невалидно.
     */
    public function complete(int $commandId, array $result): void
    {
        try {
            $this->commandRecords->update($commandId, ['result' => $result]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game wide strike command was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike command field is invalid', $exception);
        }
    }

    /**
     * Снимает команды сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteBySession(int $sessionId): void
    {
        $result = $this->commandRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('session_id', '=', $sessionId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        foreach ($result->rows() as $row) {
            $this->delete($this->rowId($row));
        }
    }

    /**
     * Тело и итог.
     *
     * @param mixed $row Строка ST.
     *
     * @return array{id: int, body: array<string, mixed>, result: array<string, mixed>} Пара.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function pair(mixed $row): array
    {
        if (
            !is_array($row)
            || !is_int($row['id'] ?? null)
            || !is_array($row['body'] ?? null)
            || !is_array($row['result'] ?? null)
        ) {
            throw new GameInvalidException('Game wide strike command row is invalid');
        }

        return ['id' => $row['id'], 'body' => $row['body'], 'result' => $row['result']];
    }

    /**
     * Удаляет строку.
     *
     * @param int $commandId Команда.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function delete(int $commandId): void
    {
        try {
            $this->commandRecords->delete($commandId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game wide strike command was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike command field is invalid', $exception);
        }
    }

    /**
     * Id строки.
     *
     * @param mixed $row Строка ST.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id битый.
     */
    private function rowId(mixed $row): int
    {
        if (!is_array($row) || !is_int($row['id'] ?? null)) {
            throw new GameInvalidException('Game wide strike command row is invalid');
        }

        return $row['id'];
    }
}
