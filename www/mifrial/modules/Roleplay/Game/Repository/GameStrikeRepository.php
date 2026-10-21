<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Строки удара 1 → 1 на общей модели действия.
 */
final class GameStrikeRepository
{
    private readonly GameActionRepository $actions;

    private readonly GameActionTargetRepository $targets;

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
        $this->actions = new GameActionRepository($smartTableGateway);
        $this->targets = new GameActionTargetRepository($smartTableGateway);
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
        $action = $this->actions->findOpenByBattle($battleId, 'strike');

        return $action === null ? null : $this->row($action);
    }

    /**
     * Последняя строка удара боя или null.
     *
     * @param int $battleId Бой.
     *
     * @return array<string, mixed>|null Колонки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findLatest(int $battleId): ?array
    {
        $action = $this->actions->findLatestByBattle($battleId, 'strike');

        return $action === null ? null : $this->row($action);
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
        $action = $this->actionFields($fields);
        $target = [
            'target_type' => $fields['defender_kind'] ?? null,
            'target_id' => $fields['defender_id'] ?? null,
            'reaction' => $fields['reaction'] ?? null,
            'block_item_inventory_id' => $fields['block_item_inventory_id'] ?? null,
            'block_item_profile_index' => $fields['block_item_profile_index'] ?? null,
            'block_item_rule_code' => $fields['block_item_rule_code'] ?? null,
            'expected_sheet_version' => $fields['expected_sheet_version'] ?? null,
        ];

        return $this->smartTableGateway->transaction(function () use ($action, $target): int {
            $actionId = $this->actions->add($action);
            $target['action_id'] = $actionId;
            $this->targets->add($target);

            return $actionId;
        });
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
    ): void {
        $target = $this->targets->findByAction($strikeId);
        if ($target === null || !is_int($target['id'] ?? null)) {
            throw new GameNotFoundException('Game strike was not found');
        }

        $targetId = $target['id'];
        $this->smartTableGateway->transaction(function () use (
            $strikeId,
            $targetId,
            $reaction,
            $blockItemInventoryId,
            $blockItemProfileIndex,
            $blockItemRuleCode,
        ): void {
            $this->targets->update($targetId, [
                'reaction' => $reaction,
                'block_item_inventory_id' => $blockItemInventoryId,
                'block_item_profile_index' => $blockItemProfileIndex,
                'block_item_rule_code' => $blockItemRuleCode,
            ]);
            $this->actions->close($strikeId);
        });
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
        $this->smartTableGateway->transaction(function () use ($sessionId): void {
            $ids = $this->actions->findIdsBySession($sessionId, 'strike');
            $this->targets->deleteByActions($ids);
            foreach ($ids as $strikeId) {
                $this->actions->delete($strikeId);
            }
        });
    }

    /**
     * Колонки действия.
     *
     * @param array<string, mixed> $fields Колонки удара.
     *
     * @return array<string, mixed> Колонки действия.
     */
    private function actionFields(array $fields): array
    {
        return [
            'kind' => 'strike',
            'battle_id' => $fields['battle_id'] ?? null,
            'session_id' => $fields['session_id'] ?? null,
            'attacker_kind' => $fields['attacker_kind'] ?? null,
            'attacker_id' => $fields['attacker_id'] ?? null,
            'action_rule_code' => $fields['action_rule_code'] ?? null,
            'item_inventory_id' => $fields['item_inventory_id'] ?? null,
            'item_rule_code' => $fields['item_rule_code'] ?? null,
            'profile_type' => $fields['profile_type'] ?? null,
            'profile_index' => $fields['profile_index'] ?? null,
            'open' => $fields['open'] ?? true,
        ];
    }

    /**
     * Строка удара в прежней форме defender_*.
     *
     * @param array<string, mixed> $action Действие.
     *
     * @return array<string, mixed> Колонки.
     *
     * @throws GameInvalidException Если цели нет.
     */
    private function row(array $action): array
    {
        $actionId = $action['id'] ?? null;
        if (!is_int($actionId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $target = $this->targets->findByAction($actionId);
        if ($target === null) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return array_merge($action, [
            'defender_kind' => $target['target_type'] ?? null,
            'defender_id' => $target['target_id'] ?? null,
            'reaction' => $target['reaction'] ?? null,
            'block_item_inventory_id' => $target['block_item_inventory_id'] ?? null,
            'block_item_profile_index' => $target['block_item_profile_index'] ?? null,
            'block_item_rule_code' => $target['block_item_rule_code'] ?? null,
            'expected_sheet_version' => $target['expected_sheet_version'] ?? null,
        ]);
    }
}
