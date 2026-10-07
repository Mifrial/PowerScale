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
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameChronicleEntryRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameChronicleEntryTable;

/**
 * Строки `game_chronicle_entry`.
 */
final class GameChronicleEntryRepository
{
    private readonly IOpenedRecords $entryRecords;

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
        $this->entryRecords = $smartTableGateway->open(GameChronicleEntryTable::class)->records();
    }

    /**
     * Вставляет запись.
     *
     * @param int $gameId Игра.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param int $offsetMinutes Смещение.
     * @param int $createdBy Автор.
     * @param DateTime $moment Момент.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если нет игры или учётки.
     * @throws GameInvalidException Если поле.
     */
    public function add(
        int $gameId,
        string $title,
        string $content,
        int $offsetMinutes,
        int $createdBy,
        DateTime $moment,
    ): int {
        try {
            return $this->entryRecords->add([
                'game_id' => $gameId,
                'title' => $title,
                'content' => $content,
                'offset_minutes' => $offsetMinutes,
                'created_by' => $createdBy,
                'created_at' => $moment,
                'updated_at' => $moment,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game chronicle entry was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game chronicle entry field is invalid', $exception);
        }
    }

    /**
     * Меняет текст и смещение.
     *
     * @param int $entryId Запись.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param int $offsetMinutes Смещение.
     * @param DateTime $moment Момент.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если поле.
     */
    public function update(
        int $entryId,
        string $title,
        string $content,
        int $offsetMinutes,
        DateTime $moment,
    ): void {
        try {
            $this->entryRecords->update($entryId, [
                'title' => $title,
                'content' => $content,
                'offset_minutes' => $offsetMinutes,
                'updated_at' => $moment,
            ]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game chronicle entry was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game chronicle entry field is invalid', $exception);
        }
    }

    /**
     * Удаляет строку.
     *
     * @param int $entryId Запись.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки уже нет.
     * @throws GameInvalidException Если удаление не удалось.
     */
    public function deleteById(int $entryId): void
    {
        try {
            $this->entryRecords->delete($entryId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game chronicle entry was not found', $exception);
        } catch (RowWriteFailedException $exception) {
            throw new GameInvalidException('Game chronicle entry field is invalid', $exception);
        }
    }

    /**
     * Одна запись.
     *
     * @param int $entryId Id.
     *
     * @return GameChronicleEntryRecord Строка.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getById(int $entryId): GameChronicleEntryRecord
    {
        $row = $this->entryRecords->getById($entryId);
        if ($row === null) {
            throw new GameNotFoundException();
        }

        return $this->record($row);
    }

    /**
     * Записи игры по смещению.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameChronicleEntryRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        $result = $this->entryRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('game_id', '=', $gameId),
            ]),
            ['offset_minutes' => 'ASC', 'id' => 'ASC'],
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
     * @return GameChronicleEntryRecord Запись.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameChronicleEntryRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Game chronicle entry row is invalid');
        }

        return GameChronicleEntryRecord::fromNormalized($row);
    }
}
