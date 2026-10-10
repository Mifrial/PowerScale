<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Dto\ConditionalCas;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IConditionalOpenedRecords;
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

    private readonly IConditionalOpenedRecords $conditionalNpcRecords;

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
        $openedTable = $smartTableGateway->open(GameNpcTable::class);
        $this->npcRecords = $openedTable->records();
        $this->conditionalNpcRecords = $openedTable->conditionalRecords();
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
            $updated = $this->conditionalNpcRecords->updateConditional(
                $record->getId(),
                new ConditionalCas('actual_version', $record->getActualVersion()),
                [
                    'name' => $name,
                    'version' => $version,
                    'visibility' => $visibility,
                ],
            );
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('NPC field is invalid', $exception);
        }

        if (!$updated) {
            return $this->conflictAfterCas($record->getId());
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
        try {
            $updated = $this->conditionalNpcRecords->updateConditional(
                $npcId,
                new ConditionalCas('actual_version', $expectedActualVersion),
                ['version' => $version],
            );
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('NPC field is invalid', $exception);
        }

        if ($updated) {
            return $this->getById($npcId);
        }

        return $this->conflictAfterCas($npcId);
    }

    /**
     * Преобразует failed CAS NPC в not-found или economy conflict.
     *
     * @param int $npcId NPC.
     *
     * @return never Не возвращает управление.
     *
     * @throws GameEconomyConflictException Если строка существует.
     * @throws GameNotFoundException Если строки нет.
     */
    private function conflictAfterCas(int $npcId): never
    {
        $row = $this->conditionalNpcRecords->getCurrentById($npcId);
        if ($row === null) {
            throw new GameNotFoundException('Game NPC was not found');
        }

        throw new GameEconomyConflictException(
            (int) $row['actual_version'],
            currentSheet: $this->sheetOf($row),
        );
    }

    /**
     * Лист из документа version.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array{choices: array<mixed>, sheet: array<mixed>} Лист.
     */
    private function sheetOf(array $row): array
    {
        $version = $row['version'] ?? [];
        if (!is_array($version)) {
            return ['choices' => [], 'sheet' => []];
        }

        $choices = $version['choices'] ?? [];
        $sheet = $version['sheet'] ?? [];

        return [
            'choices' => is_array($choices) ? $choices : [],
            'sheet' => is_array($sheet) ? $sheet : [],
        ];
    }
}
