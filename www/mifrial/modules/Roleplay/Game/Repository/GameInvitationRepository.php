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
use Mifrial\Roleplay\Game\Dto\GameInvitationRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameInvitationTable;

/**
 * Строки `game_invitation`.
 */
final class GameInvitationRepository
{
    private readonly IOpenedRecords $invitationRecords;

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
        $this->invitationRecords = $smartTableGateway->open(GameInvitationTable::class)->records();
    }

    /**
     * Вставляет pending.
     *
     * @param int $gameId Игра.
     * @param int $inviterId Кто пригласил.
     * @param int $inviteeId Кого пригласили.
     * @param DateTime $moment Момент.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если нет игры или учётки.
     * @throws GameInvalidException Если поле.
     */
    public function add(int $gameId, int $inviterId, int $inviteeId, DateTime $moment): int
    {
        try {
            return $this->invitationRecords->add([
                'game_id' => $gameId,
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
                'status' => 'pending',
                'created_at' => $moment,
                'updated_at' => $moment,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game invitation was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game invitation field is invalid', $exception);
        }
    }

    /**
     * Строка по id.
     *
     * @param int $invitationId Id.
     *
     * @return GameInvitationRecord Приглашение.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getById(int $invitationId): GameInvitationRecord
    {
        $row = $this->invitationRecords->getById($invitationId);
        if ($row === null) {
            throw new GameNotFoundException();
        }

        return $this->record($row);
    }

    /**
     * Пишет статус.
     *
     * @param int $invitationId Id.
     * @param string $status Статус.
     * @param DateTime $moment Момент.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveStatus(int $invitationId, string $status, DateTime $moment): void
    {
        try {
            $this->invitationRecords->update($invitationId, [
                'status' => $status,
                'updated_at' => $moment,
            ]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game invitation was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game invitation field is invalid', $exception);
        }
    }

    /**
     * Приглашения игры.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameInvitationRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        return $this->getList(new FilterCondition('game_id', '=', $gameId));
    }

    /**
     * Приглашения учётки.
     *
     * @param int $inviteeId Учётка.
     *
     * @return list<GameInvitationRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByInvitee(int $inviteeId): array
    {
        return $this->getList(new FilterCondition('invitee_id', '=', $inviteeId));
    }

    /**
     * Выборка по одному условию.
     *
     * @param FilterCondition $filter Условие.
     *
     * @return list<GameInvitationRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function getList(FilterCondition $filter): array
    {
        $result = $this->invitationRecords->getList(new ListQuery(
            new FilterGroup('AND', [$filter]),
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
     * @return GameInvitationRecord Приглашение.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameInvitationRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Game invitation row is invalid');
        }

        return GameInvitationRecord::fromNormalized($row);
    }
}
