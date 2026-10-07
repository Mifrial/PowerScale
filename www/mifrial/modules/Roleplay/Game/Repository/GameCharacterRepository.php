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
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameCharacterTable;

/**
 * Строки `game_character`.
 *
 * Одиннадцатый публичный метод пишет только section_visibility той же строки.
 */
final class GameCharacterRepository // phpcs:ignore MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
{
    private readonly IOpenedRecords $characterRecords;

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
        $this->characterRecords = $smartTableGateway->open(GameCharacterTable::class)->records();
    }

    /**
     * Вставляет заявку.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $characterOwnerId Владелец.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет игры, персонажа или учётки.
     * @throws GameInvalidException Если поле или живой вход занят.
     */
    public function add(int $gameId, int $characterId, int $characterOwnerId): GameCharacterRecord
    {
        return $this->getById($this->insertSubmitted($gameId, $characterId, $characterOwnerId));
    }

    /**
     * Строка пары. Живая важнее последней left.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getByPair(int $gameId, int $characterId): GameCharacterRecord
    {
        $rows = $this->rows($gameId, $characterId, null);
        $live = null;
        $latestLeft = null;
        foreach ($rows as $row) {
            if ($row->getStatus() !== 'left') {
                $live = $row;
                continue;
            }

            $latestLeft = $row;
        }

        if ($live !== null) {
            return $live;
        }

        if ($latestLeft !== null) {
            return $latestLeft;
        }

        throw new GameNotFoundException();
    }

    /**
     * Строки игры. Actual не читается.
     *
     * @param int $gameId Игра.
     * @param int|null $ownerUserId Фильтр владельца или все.
     *
     * @return list<GameCharacterRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId, ?int $ownerUserId): array
    {
        return $this->rows($gameId, null, $ownerUserId);
    }

    /**
     * Id живой строки персонажа или null.
     *
     * @param int $characterId Персонаж.
     *
     * @return int|null Id строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findLiveId(int $characterId): ?int
    {
        $result = $this->characterRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('live_character_id', '=', $characterId),
            ]),
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ));
        foreach ($result->rows() as $row) {
            return $this->record($row)->getId();
        }

        return null;
    }

    /**
     * Пишет snapshot и снимает return.
     *
     * @param int $rowId Id строки.
     * @param array<string, mixed> $snapshot Копия actual.
     * @param int $membershipRevision Новая revision.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveApproved(int $rowId, array $snapshot, int $membershipRevision): void
    {
        $this->update($rowId, [
            'status' => 'active',
            'approved_character_version' => $snapshot,
            'membership_revision' => $membershipRevision,
            'returned_at' => null,
            'return_reason' => null,
            'return_message_id' => null,
        ]);
    }

    /**
     * Пишет чат строки.
     *
     * @param int $rowId Id строки.
     * @param int $discussionChatId Чат.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveDiscussionChatId(int $rowId, int $discussionChatId): void
    {
        $this->update($rowId, ['discussion_chat_id' => $discussionChatId]);
    }

    /**
     * Пишет причину return.
     *
     * @param int $rowId Id строки.
     * @param DateTime $returnedAt Момент.
     * @param string $returnReason Причина.
     * @param int $membershipRevision Новая revision.
     * @param int|null $returnMessageId Сообщение или null.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveReturned(
        int $rowId,
        DateTime $returnedAt,
        string $returnReason,
        int $membershipRevision,
        ?int $returnMessageId,
    ): void {
        $this->update($rowId, [
            'returned_at' => $returnedAt,
            'return_reason' => $returnReason,
            'membership_revision' => $membershipRevision,
            'return_message_id' => $returnMessageId,
        ]);
    }

    /**
     * Удаляет заявку.
     *
     * @param int $rowId Id строки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если удаление не удалось.
     */
    public function deleteById(int $rowId): void
    {
        try {
            $this->characterRecords->delete($rowId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        } catch (ReferenceConstraintException | RowWriteFailedException $exception) {
            throw new GameInvalidException('Game character field is invalid', $exception);
        }
    }

    /**
     * Переводит в left и снимает живой слот.
     *
     * @param int $rowId Id строки.
     * @param int $membershipRevision Новая revision.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveLeft(int $rowId, int $membershipRevision): void
    {
        $this->update($rowId, [
            'status' => 'left',
            'live_character_id' => null,
            'membership_revision' => $membershipRevision,
        ]);
    }

    /**
     * Пишет бонус строки.
     *
     * @param int $rowId Id строки.
     * @param int $osBonus ОС.
     * @param int $orBonus ОР.
     * @param int $olBonus ОЛ.
     * @param int $membershipRevision Новая revision.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveBonus(int $rowId, int $osBonus, int $orBonus, int $olBonus, int $membershipRevision): void
    {
        $this->update($rowId, [
            'os_bonus' => $osBonus,
            'or_bonus' => $orBonus,
            'ol_bonus' => $olBonus,
            'membership_revision' => $membershipRevision,
        ]);
    }

    /**
     * Пишет коды секций строки. Остальные колонки не трогает.
     *
     * @param int $rowId Id строки.
     * @param array<int, string> $sectionVisibility Коды.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveSectionVisibility(int $rowId, array $sectionVisibility): void
    {
        $this->update($rowId, [
            'section_visibility' => $sectionVisibility,
        ]);
    }

    /**
     * Вставляет заявку и возвращает id.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $characterOwnerId Владелец.
     *
     * @return int Id строки.
     *
     * @throws GameNotFoundException Если нет игры, персонажа или учётки.
     * @throws GameInvalidException Если поле или живой вход занят.
     */
    private function insertSubmitted(int $gameId, int $characterId, int $characterOwnerId): int
    {
        try {
            return $this->characterRecords->add([
                'game_id' => $gameId,
                'character_id' => $characterId,
                'character_owner_id' => $characterOwnerId,
                'live_character_id' => $characterId,
                'status' => 'submitted',
                'approved_character_version' => null,
                'membership_revision' => 1,
                'returned_at' => null,
                'return_reason' => null,
                'os_bonus' => 0,
                'or_bonus' => 0,
                'ol_bonus' => 0,
                'section_visibility' => [],
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game character field is invalid', $exception);
        }
    }

    /**
     * Строка по id.
     *
     * @param int $rowId Id.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function getById(int $rowId): GameCharacterRecord
    {
        try {
            return $this->record($this->characterRecords->getById($rowId));
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }
    }

    /**
     * Пишет поля.
     *
     * @param int $rowId Id строки.
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    private function update(int $rowId, array $fields): void
    {
        try {
            $this->characterRecords->update($rowId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game character field is invalid', $exception);
        }
    }

    /**
     * Выборка по игре.
     *
     * @param int $gameId Игра.
     * @param int|null $characterId Персонаж или все.
     * @param int|null $ownerUserId Владелец или все.
     *
     * @return list<GameCharacterRecord> Строки по id ASC.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function rows(int $gameId, ?int $characterId, ?int $ownerUserId): array
    {
        $conditions = [new FilterCondition('game_id', '=', $gameId)];
        if ($characterId !== null) {
            $conditions[] = new FilterCondition('character_id', '=', $characterId);
        }

        if ($ownerUserId !== null) {
            $conditions[] = new FilterCondition('character_owner_id', '=', $ownerUserId);
        }

        $result = $this->characterRecords->getList(new ListQuery(
            new FilterGroup('AND', $conditions),
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
     * @return GameCharacterRecord Строка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameCharacterRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Game character row is invalid');
        }

        return GameCharacterRecord::fromNormalized($row);
    }
}
