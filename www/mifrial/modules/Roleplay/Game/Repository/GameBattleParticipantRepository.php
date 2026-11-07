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
use Mifrial\Roleplay\Game\Table\GameBattleParticipantTable;

/**
 * Строки состава боя.
 */
final class GameBattleParticipantRepository
{
    private readonly IOpenedRecords $records;

    /**
     * Создаёт репозиторий состава.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->records = $smartTableGateway->open(GameBattleParticipantTable::class)->records();
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
    public function replace(int $battleId, array $participants): void
    {
        foreach ($this->rowIds($battleId) as $rowId) {
            $this->deleteRow($rowId);
        }

        foreach ($participants as $participant) {
            $this->insert($battleId, $participant['type'], $participant['id']);
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
    public function find(int $battleId): array
    {
        $result = $this->records->getList(new ListQuery(
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
     * Снимает весь состав боя.
     *
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function deleteByBattle(int $battleId): void
    {
        foreach ($this->rowIds($battleId) as $rowId) {
            $this->deleteRow($rowId);
        }
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
    private function insert(int $battleId, string $type, int $participantId): void
    {
        try {
            $this->records->add([
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
    private function rowIds(int $battleId): array
    {
        $result = $this->records->getList(new ListQuery(
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
     * Снимает строку состава.
     *
     * @param int $rowId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function deleteRow(int $rowId): void
    {
        try {
            $this->records->delete($rowId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game battle was not found', $exception);
        } catch (RowWriteFailedException $exception) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }
    }
}
