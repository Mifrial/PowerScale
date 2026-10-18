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
use Mifrial\Roleplay\Game\Table\GameCommandTable;

/**
 * Журнал команд игры.
 */
final class GameCommandRepository
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
        $this->commandRecords = $smartTableGateway->open(GameCommandTable::class)->records();
    }

    /**
     * Строка ключа источника или null.
     *
     * @param int $gameId Игра.
     * @param string $source Источник.
     * @param string $idempotencyKey Ключ.
     *
     * @return array{id: int, body: array<string, mixed>, result: array<string, mixed>}|null Пара.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findByKey(int $gameId, string $source, string $idempotencyKey): ?array
    {
        $result = $this->commandRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('game_id', '=', $gameId),
                new FilterCondition('source', '=', $source),
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
     * Команды одной игры.
     *
     * @param int $gameId Игра.
     *
     * @return list<array{id: int, source: string, result: array<string, mixed>}> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        return $this->collect($gameId, null);
    }

    /**
     * Команды игры одного источника.
     *
     * @param int $gameId Игра.
     * @param string $source Источник.
     *
     * @return list<array{id: int, source: string, result: array<string, mixed>}> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGameAndSource(int $gameId, string $source): array
    {
        return $this->collect($gameId, $source);
    }

    /**
     * Строка id или null.
     *
     * @param int $commandId Id.
     *
     * @return array{id: int, body: array<string, mixed>, result: array<string, mixed>}|null Пара.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function find(int $commandId): ?array
    {
        $row = $this->commandRecords->getById($commandId);

        return $row === null ? null : $this->pair($row);
    }

    /**
     * Резервирует ключ до записи итогового результата.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $source Источник.
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
        string $source,
        string $idempotencyKey,
        array $body,
    ): int {
        try {
            return $this->commandRecords->add([
                'game_id' => $gameId,
                'session_id' => $sessionId,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'body' => $body,
                'result' => [],
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new GameBattleConflictException(null, 'Game command key is already used', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game command field is invalid', $exception);
        }
    }

    /**
     * Пишет готовую команду одним insert.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $source Источник.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если ключ уже есть.
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(
        int $gameId,
        int $sessionId,
        string $source,
        string $idempotencyKey,
        array $body,
        array $result,
    ): void {
        try {
            $this->commandRecords->add([
                'game_id' => $gameId,
                'session_id' => $sessionId,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'body' => $body,
                'result' => $result,
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new GameBattleConflictException(null, 'Game command key is already used', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game command field is invalid', $exception);
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
            throw new GameNotFoundException('Game command was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game command field is invalid', $exception);
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
     * Собирает страницы журнала.
     *
     * @param int $gameId Игра.
     * @param string|null $source Источник или все.
     *
     * @return list<array{id: int, source: string, result: array<string, mixed>}> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function collect(int $gameId, ?string $source): array
    {
        $commands = [];
        $offset = 0;
        do {
            $page = $this->page($gameId, $source, $offset);
            foreach ($page as $command) {
                $commands[] = $command;
            }

            $offset += 500;
        } while (count($page) === 500);

        return $commands;
    }

    /**
     * Страница журнала.
     *
     * @param int $gameId Игра.
     * @param string|null $source Источник или все.
     * @param int $offset Сдвиг.
     *
     * @return list<array{id: int, source: string, result: array<string, mixed>}> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function page(int $gameId, ?string $source, int $offset): array
    {
        $filters = [new FilterCondition('game_id', '=', $gameId)];
        if ($source !== null) {
            $filters[] = new FilterCondition('source', '=', $source);
        }

        $result = $this->commandRecords->getList(new ListQuery(
            new FilterGroup('AND', $filters),
            ['id' => 'ASC'],
            500,
            $offset,
            false,
            null,
        ));
        $commands = [];
        foreach ($result->rows() as $row) {
            $commands[] = $this->listed($row);
        }

        return $commands;
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
            throw new GameInvalidException('Game command row is invalid');
        }

        return ['id' => $row['id'], 'body' => $row['body'], 'result' => $row['result']];
    }

    /**
     * Строка списка.
     *
     * @param mixed $row Строка ST.
     *
     * @return array{id: int, source: string, result: array<string, mixed>} Строка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function listed(mixed $row): array
    {
        $pair = $this->pair($row);
        if (!is_array($row) || !is_string($row['source'] ?? null)) {
            throw new GameInvalidException('Game command row is invalid');
        }

        return ['id' => $pair['id'], 'source' => $row['source'], 'result' => $pair['result']];
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
            throw new GameNotFoundException('Game command was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game command field is invalid', $exception);
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
            throw new GameInvalidException('Game command row is invalid');
        }

        return $row['id'];
    }
}
