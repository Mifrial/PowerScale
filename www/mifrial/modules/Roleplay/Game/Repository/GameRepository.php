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
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameTable;

/**
 * Строки `game`.
 */
final class GameRepository
{
    private readonly IOpenedRecords $gameRecords;

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
        $this->gameRecords = $smartTableGateway->open(GameTable::class)->records();
    }

    /**
     * Вставляет строку.
     *
     * @param NewGame $new Поля.
     * @param string $spaceCode Код мира.
     * @param DateTime $moment Момент.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если нет владельца или мира.
     * @throws GameInvalidException Если поле.
     */
    public function add(NewGame $new, string $spaceCode, DateTime $moment): int
    {
        try {
            return $this->gameRecords->add($this->insertValues($new, $spaceCode, $moment));
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game field is invalid', $exception);
        }
    }

    /**
     * Строка по id.
     *
     * @param int $gameId Идентификатор.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getById(int $gameId): GameRecord
    {
        $row = $this->gameRecords->getById($gameId);
        if ($row === null) {
            throw new GameNotFoundException();
        }

        return GameRecord::fromNormalized($row);
    }

    /**
     * Переписывает изменяемые поля.
     *
     * @param int $gameId Идентификатор.
     * @param GamePatch $patch Поля.
     * @param DateTime $moment Момент.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function update(int $gameId, GamePatch $patch, DateTime $moment): void
    {
        try {
            $this->gameRecords->update($gameId, $this->patchValues($patch, $moment));
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game field is invalid', $exception);
        }
    }

    /**
     * Все строки или строки владельца.
     *
     * @param int $ownerUserId Владелец.
     * @param bool $viewAll Все строки.
     *
     * @return list<GameRecord> Игры.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByOwnerOrAll(int $ownerUserId, bool $viewAll): array
    {
        $filter = null;
        if (!$viewAll) {
            $filter = new FilterGroup('AND', [
                new FilterCondition('owner_id', '=', $ownerUserId),
            ]);
        }

        $result = $this->gameRecords->getList(new ListQuery(
            $filter,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $records = [];
        foreach ($result->rows() as $row) {
            if (!is_array($row)) {
                throw new GameInvalidException('Game row is invalid');
            }

            $records[] = GameRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Все строки для фильтра карточки.
     *
     * @return list<GameRecord> Игры.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListForCard(): array
    {
        $result = $this->gameRecords->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $records = [];
        foreach ($result->rows() as $row) {
            if (!is_array($row)) {
                throw new GameInvalidException('Game row is invalid');
            }

            $records[] = GameRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет id чатов стола и обсуждения.
     *
     * @param int $gameId Игра.
     * @param int $gameChatId Стол.
     * @param int $discussionChatId Обсуждение.
     *
     * @return void
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если поле.
     */
    public function saveChatIds(int $gameId, int $gameChatId, int $discussionChatId): void
    {
        try {
            $this->gameRecords->update($gameId, [
                'game_chat_id' => $gameChatId,
                'discussion_chat_id' => $discussionChatId,
            ]);
        } catch (RowNotFoundException | ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game field is invalid', $exception);
        }
    }

    /**
     * Заменяет набор whitelist.
     *
     * @param int $gameId Игра.
     * @param list<int> $userIds Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если нет игры или учётки.
     * @throws GameInvalidException Если список битый.
     */
    public function saveWhitelist(int $gameId, array $userIds): void
    {
        try {
            $this->gameRecords->update($gameId, ['whitelist' => $userIds]);
        } catch (RowNotFoundException | ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game field is invalid', $exception);
        }
    }

    /**
     * Колонки insert.
     *
     * @param NewGame $new Поля.
     * @param string $spaceCode Код мира.
     * @param DateTime $moment Момент.
     *
     * @return array<string, mixed> Колонки.
     */
    private function insertValues(NewGame $new, string $spaceCode, DateTime $moment): array
    {
        return [
            'owner_id' => $new->getOwnerUserId(),
            'name' => $new->getName(),
            'short_description' => $new->getShortDescription(),
            'description' => $new->getDescription(),
            'status' => $new->getStatus(),
            'visibility' => $new->getVisibility(),
            'join_policy' => $new->getJoinPolicy(),
            'space_id' => $new->getSpaceId(),
            'space_code' => $spaceCode,
            'rules_revision' => $new->getRulesRevision(),
            'os_points_limit' => $new->getOsPointsLimit(),
            'ol_points_limit' => $new->getOlPointsLimit(),
            'or_points_limit' => $new->getOrPointsLimit(),
            'money_limit' => $new->getMoneyLimit(),
            'whitelist' => [],
            'created_at' => $moment,
            'updated_at' => $moment,
        ];
    }

    /**
     * Колонки update.
     *
     * @param GamePatch $patch Поля.
     * @param DateTime $moment Момент.
     *
     * @return array<string, mixed> Колонки.
     */
    private function patchValues(GamePatch $patch, DateTime $moment): array
    {
        return [
            'name' => $patch->getName(),
            'short_description' => $patch->getShortDescription(),
            'description' => $patch->getDescription(),
            'status' => $patch->getStatus(),
            'visibility' => $patch->getVisibility(),
            'join_policy' => $patch->getJoinPolicy(),
            'os_points_limit' => $patch->getOsPointsLimit(),
            'ol_points_limit' => $patch->getOlPointsLimit(),
            'or_points_limit' => $patch->getOrPointsLimit(),
            'money_limit' => $patch->getMoneyLimit(),
            'rules_revision' => $patch->getRulesRevision(),
            'updated_at' => $moment,
        ];
    }
}
