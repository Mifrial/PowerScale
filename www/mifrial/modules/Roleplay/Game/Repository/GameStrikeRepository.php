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
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameStrikeTable;

/**
 * Строки удара 1 → 1.
 */
final class GameStrikeRepository
{
    private readonly IOpenedRecords $strikeRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->strikeRecords = $smartTableGateway->open(GameStrikeTable::class)->records();
    }

    /**
     * Открытый удар боя или null.
     *
     * @param int $battleId Бой.
     *
     * @return array<string, mixed>|null Колонки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findOpen(int $battleId): ?array
    {
        foreach ($this->rowsOfBattle($battleId) as $row) {
            if (($row['open'] ?? null) === true) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Вставляет открытый удар.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(array $fields): int
    {
        try {
            return $this->strikeRecords->add($fields);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game strike field is invalid', $exception);
        }
    }

    /**
     * Закрывает удар.
     *
     * @param int $strikeId Удар.
     * @param string $reaction Реакция.
     * @param int|null $blockItemInventoryId Строка инвентаря блока.
     * @param int|null $blockItemProfileIndex Индекс профиля блока.
     * @param string|null $blockItemRuleCode Код предмета блока.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function close(
        int $strikeId,
        string $reaction,
        ?int $blockItemInventoryId,
        ?int $blockItemProfileIndex,
        ?string $blockItemRuleCode,
    ): void
    {
        try {
            $this->strikeRecords->update($strikeId, [
                'reaction' => $reaction,
                'block_item_inventory_id' => $blockItemInventoryId,
                'block_item_profile_index' => $blockItemProfileIndex,
                'block_item_rule_code' => $blockItemRuleCode,
                'open' => false,
            ]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game strike was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game strike field is invalid', $exception);
        }
    }

    /**
     * Снимает удары сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteBySession(int $sessionId): void
    {
        $result = $this->strikeRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('session_id', '=', $sessionId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        foreach ($result->rows() as $row) {
            $this->delete($this->rowId($row));
        }
    }

    /**
     * Строки одного боя.
     *
     * @param int $battleId Бой.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function rowsOfBattle(int $battleId): array
    {
        $result = $this->strikeRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('battle_id', '=', $battleId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $rows = [];
        foreach ($result->rows() as $row) {
            if (!is_array($row)) {
                throw new GameInvalidException('Game strike row is invalid');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Удаляет строку.
     *
     * @param int $strikeId Удар.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function delete(int $strikeId): void
    {
        try {
            $this->strikeRecords->delete($strikeId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game strike was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game strike field is invalid', $exception);
        }
    }

    /**
     * Id строки.
     *
     * @param mixed $row Строка ST.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id битый.
     */
    private function rowId(mixed $row): int
    {
        if (!is_array($row) || !is_int($row['id'] ?? null)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $row['id'];
    }
}
