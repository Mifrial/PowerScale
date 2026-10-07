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
use Mifrial\Roleplay\Game\Dto\GameDeliveryRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;

/**
 * Строки доставки. Повтор пары источник+id журнала строку не пишет.
 */
final class GameDeliveryRepository
{
    private readonly IOpenedRecords $deliveryRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->deliveryRecords = $smartTableGateway->open(GameDeliveryTable::class)->records();
    }

    /**
     * Пишет строку, если пары ещё нет.
     *
     * @param int $gameId Игра.
     * @param string $source Источник economy или strike.
     * @param int $sourceId Id журнала.
     * @param array<int, array<string, mixed>> $keys Ключи.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    public function add(int $gameId, string $source, int $sourceId, array $keys): void
    {
        if ($this->findId($gameId, $source, $sourceId) !== null) {
            return;
        }

        try {
            $this->deliveryRecords->add([
                'game_id' => $gameId,
                'source' => $source,
                'source_id' => $sourceId,
                'keys' => $keys,
            ]);
        } catch (UniqueConstraintException) {
            return;
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game delivery field is invalid', $exception);
        }
    }

    /**
     * Максимальный курсор игры или 0.
     *
     * @param int $gameId Игра.
     *
     * @return int Курсор.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getMaxCursor(int $gameId): int
    {
        $result = $this->deliveryRecords->getList(new ListQuery(
            new FilterGroup('AND', [new FilterCondition('game_id', '=', $gameId)]),
            ['id' => 'DESC'],
            1,
            0,
            false,
            null,
        ));
        $rows = $result->rows();
        if ($rows === [] || !is_array($rows[0])) {
            return 0;
        }

        return GameDeliveryRecord::fromNormalized($rows[0])->getId();
    }

    /**
     * Строки с курсором строго больше границы.
     *
     * @param int $gameId Игра.
     * @param int $afterCursor Нижняя граница.
     *
     * @return array<int, GameDeliveryRecord> Хвост.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getAfter(int $gameId, int $afterCursor): array
    {
        return $this->rows($gameId, $afterCursor);
    }

    /**
     * Id уже лежащей пары.
     *
     * @param int $gameId Игра.
     * @param string $source Источник.
     * @param int $sourceId Id журнала.
     *
     * @return int|null Id или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function findId(int $gameId, string $source, int $sourceId): ?int
    {
        $result = $this->deliveryRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('game_id', '=', $gameId),
                new FilterCondition('source', '=', $source),
                new FilterCondition('source_id', '=', $sourceId),
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

        return GameDeliveryRecord::fromNormalized($rows[0])->getId();
    }

    /**
     * Строки игры.
     *
     * @param int $gameId Игра.
     * @param int|null $afterCursor Нижняя граница, если есть.
     *
     * @return array<int, GameDeliveryRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function rows(int $gameId, ?int $afterCursor): array
    {
        $filters = [new FilterCondition('game_id', '=', $gameId)];
        if ($afterCursor !== null && $afterCursor >= 1) {
            $filters[] = new FilterCondition('id', '>', $afterCursor);
        }

        $result = $this->deliveryRecords->getList(new ListQuery(
            new FilterGroup('AND', $filters),
            ['id' => 'ASC'],
            500,
            0,
            false,
            null,
        ));
        $records = [];
        foreach ($result->rows() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $record = GameDeliveryRecord::fromNormalized($row);
            if ($afterCursor !== null && $record->getId() <= $afterCursor) {
                continue;
            }

            $records[] = $record;
        }

        return $records;
    }
}
