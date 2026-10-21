<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Цели одного широкого удара на общей модели действия.
 */
final class GameWideStrikeTargetRepository
{
    private readonly GameActionTargetRepository $targets;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->targets = new GameActionTargetRepository($smartTableGateway);
    }

    /**
     * Цели удара по id в прежней форме defender_*.
     *
     * @param int $strikeId Удар.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getList(int $strikeId): array
    {
        $rows = [];
        foreach ($this->targets->getList($strikeId) as $row) {
            $rows[] = [
                'id' => $row['id'] ?? null,
                'strike_id' => $row['action_id'] ?? null,
                'defender_kind' => $row['target_type'] ?? null,
                'defender_id' => $row['target_id'] ?? null,
                'reaction' => $row['reaction'] ?? null,
                'block_item_inventory_id' => $row['block_item_inventory_id'] ?? null,
                'block_item_profile_index' => $row['block_item_profile_index'] ?? null,
                'block_item_rule_code' => $row['block_item_rule_code'] ?? null,
                'expected_sheet_version' => $row['expected_sheet_version'] ?? null,
            ];
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
        return $this->targets->add([
            'action_id' => $fields['strike_id'] ?? null,
            'target_type' => $fields['defender_kind'] ?? null,
            'target_id' => $fields['defender_id'] ?? null,
            'reaction' => $fields['reaction'] ?? null,
            'block_item_inventory_id' => $fields['block_item_inventory_id'] ?? null,
            'block_item_profile_index' => $fields['block_item_profile_index'] ?? null,
            'block_item_rule_code' => $fields['block_item_rule_code'] ?? null,
            'expected_sheet_version' => $fields['expected_sheet_version'] ?? null,
        ]);
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
    ): void {
        $this->targets->update($targetId, [
            'reaction' => $reaction,
            'block_item_inventory_id' => $blockItemInventoryId,
            'block_item_profile_index' => $blockItemProfileIndex,
            'block_item_rule_code' => $blockItemRuleCode,
        ]);
    }

    /**
     * Сохраняет ответ цели вместе с ожидаемой версией листа.
     *
     * @param int $targetId Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Ответ.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function answer(int $targetId, array $choice): void
    {
        $this->targets->update($targetId, [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'],
            'block_item_profile_index' => $choice['blockItemProfileIndex'],
            'block_item_rule_code' => $choice['blockItemRuleCode'],
            'expected_sheet_version' => $choice['expectedSheetVersion'],
        ]);
    }

    /**
     * Снимает цели ударов.
     *
     * @param array<int, int> $strikeIds Удары.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteByStrikes(array $strikeIds): void
    {
        $this->targets->deleteByActions($strikeIds);
    }
}
