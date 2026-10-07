<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameEconomyOperationRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Table\GameEconomyOperationTable;

/**
 * Строки проведённых операций.
 */
final class GameEconomyOperationRepository
{
    private readonly IOpenedRecords $operationRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->operationRecords = $smartTableGateway->open(GameEconomyOperationTable::class)->records();
    }

    /**
     * Операции одной игры.
     *
     * @param int $gameId Игра.
     *
     * @return array<int, GameEconomyOperationRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        $records = [];
        $offset = 0;
        do {
            $result = $this->operationRecords->getList(new ListQuery(
                new FilterGroup('AND', [
                    new FilterCondition('game_id', '=', $gameId),
                ]),
                ['id' => 'ASC'],
                500,
                $offset,
                false,
                null,
            ));
            $page = 0;
            foreach ($result->rows() as $row) {
                if (is_array($row)) {
                    $records[] = $this->record($row);
                    $page++;
                }
            }

            $offset += 500;
        } while ($page === 500);

        return $records;
    }

    /**
     * Строка по ключу повтора.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     *
     * @return GameEconomyOperationRecord|null Строка или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findByKey(int $gameId, string $idempotencyKey): ?GameEconomyOperationRecord
    {
        $result = $this->operationRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('game_id', '=', $gameId),
                new FilterCondition('idempotency_key', '=', $idempotencyKey),
            ]),
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ));
        $rows = $result->rows();
        if ($rows === []) {
            return null;
        }

        return $this->record($rows[0]);
    }

    /**
     * Пишет итог. Повторный ключ — конфликт.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return GameEconomyOperationRecord Строка.
     *
     * @throws GameEconomyConflictException Если ключ уже есть.
     * @throws GameInvalidException Если поле.
     */
    public function add(
        int $gameId,
        string $idempotencyKey,
        array $body,
        array $result,
    ): GameEconomyOperationRecord {
        try {
            $id = $this->operationRecords->add([
                'game_id' => $gameId,
                'idempotency_key' => $idempotencyKey,
                'body' => $body,
                'result' => $result,
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new GameEconomyConflictException(null, 'Game economy key is already used', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Economy operation field is invalid', $exception);
        }

        $stored = $this->findByKey($gameId, $idempotencyKey);
        if (!$stored instanceof GameEconomyOperationRecord || $stored->getId() !== $id) {
            throw new GameInvalidException('Economy operation was not stored');
        }

        return $stored;
    }

    /**
     * Запись из строки.
     *
     * @param mixed $row Строка ST.
     *
     * @return GameEconomyOperationRecord Операция.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameEconomyOperationRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Economy operation row is invalid');
        }

        return GameEconomyOperationRecord::fromNormalized($row);
    }
}
