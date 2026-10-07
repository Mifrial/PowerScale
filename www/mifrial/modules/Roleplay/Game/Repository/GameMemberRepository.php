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
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameMemberTable;

/**
 * Строки `game_member`.
 */
final class GameMemberRepository
{
    private readonly IOpenedRecords $memberRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $smartTableGateway,
    ) {
        $this->memberRecords = $smartTableGateway->open(GameMemberTable::class)->records();
    }

    /**
     * Вставляет участника.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $role Роль.
     *
     * @return int Id строки.
     *
     * @throws GameNotFoundException Если нет игры или учётки.
     * @throws GameInvalidException Если поле или пара занята.
     */
    public function add(int $gameId, int $userId, string $role): int
    {
        try {
            return $this->memberRecords->add([
                'game_id' => $gameId,
                'user_id' => $userId,
                'role' => $role,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game member was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game member field is invalid', $exception);
        }
    }

    /**
     * Строка пары.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return GameMemberRecord Участник.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getByPair(int $gameId, int $userId): GameMemberRecord
    {
        $row = $this->memberRecords->getUnique(ListQuery::fromOptions([
            'filter' => [
                'game_id' => $gameId,
                'user_id' => $userId,
            ],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new GameNotFoundException();
        }

        return $this->record($row);
    }

    /**
     * Пишет роль.
     *
     * @param int $memberId Id строки.
     * @param string $role Роль.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function updateRole(int $memberId, string $role): void
    {
        try {
            $this->memberRecords->update($memberId, ['role' => $role]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game member was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game member field is invalid', $exception);
        }
    }

    /**
     * Снимает строку.
     *
     * @param int $memberId Id строки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если удаление не удалось.
     */
    public function deleteById(int $memberId): void
    {
        try {
            $this->memberRecords->delete($memberId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game member was not found', $exception);
        } catch (ReferenceConstraintException | RowWriteFailedException $exception) {
            throw new GameInvalidException('Game member field is invalid', $exception);
        }
    }

    /**
     * Участники игры.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameMemberRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        $result = $this->memberRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('game_id', '=', $gameId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $records = [];
        foreach ($result->rows() as $row) {
            $records[] = $this->record($row);
        }

        return $records;
    }

    /**
     * Строки одной учётки.
     *
     * @param int $userId Учётка.
     *
     * @return list<GameMemberRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByUser(int $userId): array
    {
        $result = $this->memberRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('user_id', '=', $userId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $records = [];
        foreach ($result->rows() as $row) {
            $records[] = $this->record($row);
        }

        return $records;
    }

    /**
     * Запись из строки.
     *
     * @param mixed $row Строка ST.
     *
     * @return GameMemberRecord Участник.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameMemberRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Game member row is invalid');
        }

        return GameMemberRecord::fromNormalized($row);
    }
}
