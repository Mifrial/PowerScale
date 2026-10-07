<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameShopPositionRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameShopPositionTable;

/**
 * Строки магазина игры.
 */
final class GameShopPositionRepository
{
    private readonly IOpenedRecords $positionRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->positionRecords = $smartTableGateway->open(GameShopPositionTable::class)->records();
    }

    /**
     * Позиции игры.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameShopPositionRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId): array
    {
        $result = $this->positionRecords->getList(new ListQuery(
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
     * Вставляет позицию с version 1.
     *
     * @param int $gameId Игра.
     * @param string $ruleCode Код.
     * @param int $buyPrice Цена покупки.
     * @param int|null $sellPrice Цена выкупа.
     * @param int $quantity Остаток.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    public function add(int $gameId, string $ruleCode, int $buyPrice, ?int $sellPrice, int $quantity): void
    {
        $this->write(function () use ($gameId, $ruleCode, $buyPrice, $sellPrice, $quantity): void {
            $this->positionRecords->add([
                'game_id' => $gameId,
                'rule_code' => $ruleCode,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'quantity' => $quantity,
                'version' => 1,
            ]);
        });
    }

    /**
     * Пишет цены, остаток и version + 1.
     *
     * @param GameShopPositionRecord $record Текущая строка.
     * @param int $buyPrice Цена покупки.
     * @param int|null $sellPrice Цена выкупа.
     * @param int $quantity Остаток.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    public function save(GameShopPositionRecord $record, int $buyPrice, ?int $sellPrice, int $quantity): void
    {
        $this->write(function () use ($record, $buyPrice, $sellPrice, $quantity): void {
            $this->positionRecords->update($record->getId(), [
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'quantity' => $quantity,
                'version' => $record->getVersion() + 1,
            ]);
        });
    }

    /**
     * Удаляет строку.
     *
     * @param int $positionId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function delete(int $positionId): void
    {
        try {
            $this->positionRecords->delete($positionId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Shop position was not found', $exception);
        } catch (RowWriteFailedException $exception) {
            throw new GameInvalidException('Shop field is invalid', $exception);
        }
    }

    /**
     * Запись из строки.
     *
     * @param mixed $row Строка ST.
     *
     * @return GameShopPositionRecord Позиция.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function record(mixed $row): GameShopPositionRecord
    {
        if (!is_array($row)) {
            throw new GameInvalidException('Shop row is invalid');
        }

        return GameShopPositionRecord::fromNormalized($row);
    }

    /**
     * Пишет строку и переводит ошибки поля.
     *
     * @param callable $writer Запись.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    private function write(callable $writer): void
    {
        try {
            $writer();
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Shop field is invalid', $exception);
        }
    }
}
