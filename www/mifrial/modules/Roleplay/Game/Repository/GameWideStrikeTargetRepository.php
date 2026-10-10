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
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTargetTable;

/**
 * Цели одного широкого удара.
 */
final class GameWideStrikeTargetRepository
{
    private readonly IOpenedRecords $targetRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->targetRecords = $smartTableGateway->open(GameWideStrikeTargetTable::class)->records();
    }

    /**
     * Цели удара по id.
     *
     * @param int $strikeId Удар.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getList(int $strikeId): array
    {
        $result = $this->targetRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('strike_id', '=', $strikeId),
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
                throw new GameInvalidException('Game wide strike target row is invalid');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Вставляет цель.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если поле.
     */
    public function add(array $fields): int
    {
        try {
            return $this->targetRecords->add($fields);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike target field is invalid', $exception);
        }
    }

    /**
     * Пишет реакцию цели.
     *
     * @param int $targetId Цель.
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
        int $targetId,
        string $reaction,
        ?int $blockItemInventoryId,
        ?int $blockItemProfileIndex,
        ?string $blockItemRuleCode,
    ): void
    {
        try {
            $this->targetRecords->update($targetId, [
                'reaction' => $reaction,
                'block_item_inventory_id' => $blockItemInventoryId,
                'block_item_profile_index' => $blockItemProfileIndex,
                'block_item_rule_code' => $blockItemRuleCode,
            ]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game wide strike target was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike target field is invalid', $exception);
        }
    }

    /**
     * Снимает цели ударов.
     *
     * @param list<int> $strikeIds Удары.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteByStrikes(array $strikeIds): void
    {
        foreach ($strikeIds as $strikeId) {
            foreach ($this->getList($strikeId) as $row) {
                $this->delete($this->rowId($row));
            }
        }
    }

    /**
     * Удаляет строку.
     *
     * @param int $targetId Цель.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function delete(int $targetId): void
    {
        try {
            $this->targetRecords->delete($targetId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game wide strike target was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game wide strike target field is invalid', $exception);
        }
    }

    /**
     * Id строки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id битый.
     */
    private function rowId(array $row): int
    {
        if (!is_int($row['id'] ?? null)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return $row['id'];
    }
}
