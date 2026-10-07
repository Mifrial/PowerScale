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
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameBattleParticipantTable;
use Mifrial\Roleplay\Game\Table\GameBattleTable;

/**
 * Строки открытого боя и его состава.
 */
final class GameBattleRepository
{
    private readonly IOpenedRecords $battleRecords;

    private readonly IOpenedRecords $participantRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->battleRecords = $smartTableGateway->open(GameBattleTable::class)->records();
        $this->participantRecords = $smartTableGateway->open(GameBattleParticipantTable::class)->records();
    }

    /**
     * Открывает бой с версией 1.
     *
     * @param int $sessionId Сессия.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(int $sessionId): GameBattleRecord
    {
        try {
            $id = $this->battleRecords->add([
                'session_id' => $sessionId,
                'state_version' => 1,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }

        return $this->require($id);
    }

    /**
     * Бой по id или null.
     *
     * @param int $battleId Бой.
     *
     * @return GameBattleRecord|null Строка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function find(int $battleId): ?GameBattleRecord
    {
        $row = $this->battleRecords->getById($battleId);
        if ($row === null) {
            return null;
        }

        return GameBattleRecord::fromNormalized($row);
    }

    /**
     * Id боёв сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findIdsBySession(int $sessionId): array
    {
        $result = $this->battleRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('session_id', '=', $sessionId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $ids = [];
        foreach ($result->rows() as $row) {
            $ids[] = $this->rowId($row);
        }

        return $ids;
    }

    /**
     * Увеличивает версию, если она совпала.
     *
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return GameBattleRecord После записи.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function advanceVersion(int $battleId, int $expectedVersion): GameBattleRecord
    {
        $current = $this->require($battleId);
        if ($current->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($current->getStateVersion());
        }

        try {
            $this->battleRecords->update($battleId, [
                'state_version' => $expectedVersion + 1,
            ]);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }

        return $this->require($battleId);
    }

    /**
     * Пишет порядок уже открытого боя.
     *
     * @param int $battleId Бой.
     * @param array<int, array<string, mixed>> $turnOrder Список.
     *
     * @return void
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function replaceTurnOrder(int $battleId, array $turnOrder): void
    {
        $this->require($battleId);

        try {
            $this->battleRecords->update($battleId, [
                'turn_order' => $turnOrder,
            ]);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }
    }

    /**
     * Заменяет состав боя.
     *
     * @param int $battleId Бой.
     * @param list<array{type: string, id: int}> $participants Пары.
     *
     * @return void
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function replaceParticipants(int $battleId, array $participants): void
    {
        foreach ($this->participantRowIds($battleId) as $rowId) {
            $this->deleteRow($this->participantRecords, $rowId);
        }

        foreach ($participants as $participant) {
            $this->insertParticipant($battleId, $participant['type'], $participant['id']);
        }
    }

    /**
     * Снимает состав и строку боя.
     *
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function delete(int $battleId): void
    {
        foreach ($this->participantRowIds($battleId) as $rowId) {
            $this->deleteRow($this->participantRecords, $rowId);
        }

        $this->deleteRow($this->battleRecords, $battleId);
    }

    /**
     * Снимает все бои сессии.
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
        foreach ($this->findIdsBySession($sessionId) as $battleId) {
            $this->delete($battleId);
        }
    }

    /**
     * Состав боя.
     *
     * @param int $battleId Бой.
     *
     * @return list<array{type: string, id: int}> Пары.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findParticipants(int $battleId): array
    {
        $result = $this->participantRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('battle_id', '=', $battleId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $participants = [];
        foreach ($result->rows() as $row) {
            $participants[] = $this->participant($row);
        }

        return $participants;
    }

    /**
     * Число строк состава.
     *
     * @param int $battleId Бой.
     *
     * @return int Число.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function countParticipants(int $battleId): int
    {
        return count($this->participantRowIds($battleId));
    }

    /**
     * Бой или отказ.
     *
     * @param int $battleId Бой.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function require(int $battleId): GameBattleRecord
    {
        $battle = $this->find($battleId);
        if (!$battle instanceof GameBattleRecord) {
            throw new GameNotFoundException('Game battle was not found');
        }

        return $battle;
    }

    /**
     * Вставляет участника.
     *
     * @param int $battleId Бой.
     * @param string $type character или npc.
     * @param int $participantId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    private function insertParticipant(int $battleId, string $type, int $participantId): void
    {
        try {
            $this->participantRecords->add([
                'battle_id' => $battleId,
                'kind' => $type,
                'subject_id' => $participantId,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game battle was not found', $exception);
        } catch (UniqueConstraintException $exception) {
            throw new GameInvalidException('Game battle participant is duplicated', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle participant field is invalid', $exception);
        }
    }

    /**
     * Пара из строки состава.
     *
     * @param mixed $row Строка ST.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если поле битое.
     */
    private function participant(mixed $row): array
    {
        if (!is_array($row) || !is_string($row['kind'] ?? null) || !is_int($row['subject_id'] ?? null)) {
            throw new GameInvalidException('Game battle participant row is invalid');
        }

        return ['type' => $row['kind'], 'id' => $row['subject_id']];
    }

    /**
     * Id строк состава.
     *
     * @param int $battleId Бой.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function participantRowIds(int $battleId): array
    {
        $result = $this->participantRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('battle_id', '=', $battleId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $ids = [];
        foreach ($result->rows() as $row) {
            $ids[] = $this->rowId($row);
        }

        return $ids;
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
            throw new GameInvalidException('Game battle row is invalid');
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
            throw new GameNotFoundException('Game battle was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }
    }
}
