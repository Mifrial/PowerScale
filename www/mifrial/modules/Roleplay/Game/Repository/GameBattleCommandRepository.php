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
use Mifrial\Roleplay\Game\Dto\GameBattleCommandRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameBattleCommandTable;

/**
 * Строки команд боя.
 */
final class GameBattleCommandRepository
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
        $this->commandRecords = $smartTableGateway->open(GameBattleCommandTable::class)->records();
    }

    /**
     * Строка ключа или null.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     *
     * @return GameBattleCommandRecord|null Строка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findByKey(int $gameId, string $idempotencyKey): ?GameBattleCommandRecord
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

        return $this->record($rows[0]);
    }

    /**
     * Пишет итог. Повторный ключ — конфликт.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если ключ уже есть.
     * @throws GameNotFoundException Если игры или сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(
        int $gameId,
        int $sessionId,
        string $idempotencyKey,
        array $body,
        array $result,
    ): void {
        try {
            $this->commandRecords->add([
                'game_id' => $gameId,
                'session_id' => $sessionId,
                'idempotency_key' => $idempotencyKey,
                'body' => $body,
                'result' => $result,
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new GameBattleConflictException(null, 'Game battle key is already used', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle command field is invalid', $exception);
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
     * Запись из строки.
     *
     * @param mixed $row Строка ST.
     *
     * @return GameBattleCommandRecord Запись.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameBattleCommandRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Game battle command row is invalid');
        }

        return GameBattleCommandRecord::fromNormalized($row);
    }

    /**
     * Id одной строки списка.
     *
     * @param mixed $row Строка ST.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id нет.
     */
    private function rowId(mixed $row): int
    {
        if (!is_array($row) || !is_int($row['id'] ?? null)) {
            throw new GameInvalidException('Game battle command row is invalid');
        }

        return $row['id'];
    }

    /**
     * Снимает строку.
     *
     * @param int $rowId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function delete(int $rowId): void
    {
        try {
            $this->commandRecords->delete($rowId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game battle command was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle command field is invalid', $exception);
        }
    }
}
