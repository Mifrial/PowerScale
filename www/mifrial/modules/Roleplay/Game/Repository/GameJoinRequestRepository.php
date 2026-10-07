<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\Kernel\Value\DateTime;
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
use Mifrial\Roleplay\Game\Dto\GameJoinRequestRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameJoinRequestTable;

/**
 * Строки `game_join_request`.
 */
final class GameJoinRequestRepository
{
    private readonly IOpenedRecords $requestRecords;

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
        $this->requestRecords = $smartTableGateway->open(GameJoinRequestTable::class)->records();
    }

    /**
     * Вставляет pending.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param DateTime $moment Момент.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если нет игры или учётки.
     * @throws GameInvalidException Если поле.
     */
    public function add(int $gameId, int $userId, DateTime $moment): int
    {
        try {
            return $this->requestRecords->add([
                'game_id' => $gameId,
                'user_id' => $userId,
                'status' => 'pending',
                'created_at' => $moment,
                'updated_at' => $moment,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game join request was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game join request field is invalid', $exception);
        }
    }

    /**
     * Заявки игры.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameJoinRequestRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        $result = $this->requestRecords->getList(new ListQuery(
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
     * Живая заявка пары или null.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return GameJoinRequestRecord|null Заявка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findPending(int $gameId, int $userId): ?GameJoinRequestRecord
    {
        foreach ($this->getListByGame($gameId) as $record) {
            if ($record->getUserId() === $userId && $record->getStatus() === 'pending') {
                return $record;
            }
        }

        return null;
    }

    /**
     * Пишет статус.
     *
     * @param int $requestId Id.
     * @param string $status Статус.
     * @param DateTime $moment Момент.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveStatus(int $requestId, string $status, DateTime $moment): void
    {
        try {
            $this->requestRecords->update($requestId, [
                'status' => $status,
                'updated_at' => $moment,
            ]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game join request was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game join request field is invalid', $exception);
        }
    }

    /**
     * Запись из строки.
     *
     * @param mixed $row Строка ST.
     *
     * @return GameJoinRequestRecord Заявка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameJoinRequestRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Game join request row is invalid');
        }

        return GameJoinRequestRecord::fromNormalized($row);
    }
}
