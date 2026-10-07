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
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Exception\GameSessionConflictException;
use Mifrial\Roleplay\Game\Table\GameSessionCharacterTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;

/**
 * Строки текущей сессии и её состава.
 */
final class GameSessionRepository
{
    private readonly IOpenedRecords $sessionRecords;

    private readonly IOpenedRecords $participantRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
        $this->sessionRecords = $smartTableGateway->open(GameSessionTable::class)->records();
        $this->participantRecords = $smartTableGateway->open(GameSessionCharacterTable::class)->records();
    }

    /**
     * Id текущей сессии или null.
     *
     * @param int $gameId Игра.
     *
     * @return int|null Id строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findSessionId(int $gameId): ?int
    {
        return $this->firstId($this->sessionRecords, 'game_id', $gameId);
    }

    /**
     * Пара есть в составе.
     *
     * @param int $sessionId Сессия.
     * @param int $characterId Персонаж.
     *
     * @return bool true, если строка состава есть.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function hasParticipant(int $sessionId, int $characterId): bool
    {
        $rows = $this->participantRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('session_id', '=', $sessionId),
                new FilterCondition('character_id', '=', $characterId),
            ]),
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ));

        return $rows->rows() !== [];
    }

    /**
     * Персонаж есть в составе любой текущей сессии.
     *
     * @param int $characterId Персонаж.
     *
     * @return bool true, если строка состава есть.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function hasCharacter(int $characterId): bool
    {
        return $this->firstId($this->participantRecords, 'character_id', $characterId) !== null;
    }

    /**
     * Пишет сессию и состав одной транзакцией.
     *
     * @param int $gameId Игра.
     * @param list<int> $characterIds Снимок входа.
     *
     * @return void
     *
     * @throws GameSessionConflictException Если сессия этой игры уже есть.
     * @throws GameNotFoundException Если нет игры или персонажа.
     * @throws GameInvalidException Если поле.
     */
    public function addSession(int $gameId, array $characterIds): void
    {
        $this->smartTableGateway->transaction(function () use ($gameId, $characterIds): void {
            $sessionId = $this->insertSession($gameId);
            foreach ($characterIds as $characterId) {
                $this->insertParticipant($sessionId, $characterId);
            }
        });
    }

    /**
     * Снимает состав и сессию одной транзакцией.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteSession(int $sessionId): void
    {
        $this->smartTableGateway->transaction(function () use ($sessionId): void {
            foreach ($this->participantIds($sessionId) as $rowId) {
                $this->deleteRow($this->participantRecords, $rowId);
            }

            $this->deleteRow($this->sessionRecords, $sessionId);
        });
    }

    /**
     * Вставляет строку сессии.
     *
     * @param int $gameId Игра.
     *
     * @return int Id.
     *
     * @throws GameSessionConflictException Если сессия уже есть.
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если поле.
     */
    private function insertSession(int $gameId): int
    {
        try {
            return $this->sessionRecords->add([
                'game_id' => $gameId,
                'status' => 'playing',
                'state_version' => 1,
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new GameSessionConflictException('Game session is already running', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game session field is invalid', $exception);
        }
    }

    /**
     * Вставляет строку состава.
     *
     * @param int $sessionId Сессия.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws GameNotFoundException Если нет сессии или персонажа.
     * @throws GameInvalidException Если поле.
     */
    private function insertParticipant(int $sessionId, int $characterId): void
    {
        try {
            $this->participantRecords->add([
                'session_id' => $sessionId,
                'character_id' => $characterId,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session character was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game session character field is invalid', $exception);
        }
    }

    /**
     * Id строк состава.
     *
     * @param int $sessionId Сессия.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function participantIds(int $sessionId): array
    {
        $result = $this->participantRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('session_id', '=', $sessionId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $rowIds = [];
        foreach ($result->rows() as $row) {
            $rowIds[] = $this->rowId($row);
        }

        return $rowIds;
    }

    /**
     * Первый id по равенству колонки.
     *
     * @param IOpenedRecords $records Таблица.
     * @param string $column Колонка.
     * @param int $value Значение.
     *
     * @return int|null Id или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function firstId(IOpenedRecords $records, string $column, int $value): ?int
    {
        $result = $records->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition($column, '=', $value),
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

        return $this->rowId($rows[0]);
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
            throw new GameInvalidException('Game session row is invalid');
        }

        return $row['id'];
    }

    /**
     * Снимает строку.
     *
     * @param IOpenedRecords $records Таблица.
     * @param int $rowId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function deleteRow(IOpenedRecords $records, int $rowId): void
    {
        try {
            $records->delete($rowId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game session field is invalid', $exception);
        }
    }
}
