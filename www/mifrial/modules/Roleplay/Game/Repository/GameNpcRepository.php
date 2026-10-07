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
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameNpcTable;

/**
 * Строки NPC игры.
 */
final class GameNpcRepository
{
    private readonly IOpenedRecords $npcRecords;

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
        $this->npcRecords = $smartTableGateway->open(GameNpcTable::class)->records();
    }

    /**
     * Вставляет NPC.
     *
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array<string, mixed> $version Лист.
     * @param array<string, mixed> $visibility Видимость.
     *
     * @return GameNpcRecord Строка.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(int $gameId, string $name, array $version, array $visibility): GameNpcRecord
    {
        try {
            $npcId = $this->npcRecords->add([
                'game_id' => $gameId,
                'name' => $name,
                'version' => $version,
                'actual_version' => 1,
                'visibility' => $visibility,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('NPC field is invalid', $exception);
        }

        return $this->getById($npcId);
    }

    /**
     * Строка по id.
     *
     * @param int $npcId NPC.
     *
     * @return GameNpcRecord Строка.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getById(int $npcId): GameNpcRecord
    {
        $row = $this->npcRecords->getById($npcId);
        if ($row === null) {
            throw new GameNotFoundException();
        }

        return GameNpcRecord::fromNormalized($row);
    }

    /**
     * Строки одной игры.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameNpcRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        $result = $this->npcRecords->getList(new ListQuery(
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
            $records[] = GameNpcRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет лист и увеличивает CAS.
     *
     * @param GameNpcRecord $record Текущая строка.
     * @param string $name Имя.
     * @param array<string, mixed> $version Новый лист.
     * @param array<string, mixed> $visibility Видимость.
     *
     * @return GameNpcRecord После записи.
     *
     * @throws GameInvalidException Если поле.
     * @throws GameNotFoundException Если строки нет.
     */
    public function save(GameNpcRecord $record, string $name, array $version, array $visibility): GameNpcRecord
    {
        try {
            $this->npcRecords->update($record->getId(), [
                'name' => $name,
                'version' => $version,
                'actual_version' => $record->getActualVersion() + 1,
                'visibility' => $visibility,
            ]);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('NPC field is invalid', $exception);
        }

        return $this->getById($record->getId());
    }

    /**
     * Пишет лист, если actual_version совпал.
     *
     * @param int $npcId NPC.
     * @param array<string, mixed> $version Новый лист.
     * @param int $expectedActualVersion Текущий счётчик.
     *
     * @return GameNpcRecord После записи.
     *
     * @throws GameEconomyConflictException Если версия другая.
     * @throws GameInvalidException Если поле.
     * @throws GameNotFoundException Если строки нет.
     */
    public function replaceVersion(int $npcId, array $version, int $expectedActualVersion): GameNpcRecord
    {
        $current = $this->getById($npcId);
        if ($current->getActualVersion() !== $expectedActualVersion) {
            throw new GameEconomyConflictException($current->getActualVersion());
        }

        try {
            $this->npcRecords->update($npcId, [
                'version' => $version,
                'actual_version' => $expectedActualVersion + 1,
            ]);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('NPC field is invalid', $exception);
        }

        return $this->getById($npcId);
    }
}
