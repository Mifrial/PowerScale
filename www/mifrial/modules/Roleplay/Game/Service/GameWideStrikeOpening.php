<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Строки открытого широкого удара.
 */
final class GameWideStrikeOpening
{
    /**
     * Создаёт построитель.
     *
     * @param GameActionRepository $actions Действия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $actions,
    ) {
    }

    /**
     * Колонки открытого удара.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $sessionId Сессия.
     * @param array $choice Выбор.
     *
     * @return array<string, mixed> Строка.
     */
    public function row(GameBattleRecord $battle, int $sessionId, array $choice): array
    {
        return [
            'battle_id' => $battle->getId(),
            'session_id' => $sessionId,
            'attacker_kind' => $choice['attacker']['type'],
            'attacker_id' => $choice['attacker']['id'],
            'action_rule_code' => $choice['actionRuleCode'],
            'item_inventory_id' => $choice['itemInventoryId'],
            'item_rule_code' => $choice['itemRuleCode'],
            'profile_type' => $choice['profileType'],
            'profile_index' => $choice['profileIndex'],
            'open' => true,
        ];
    }

    /**
     * Строки целей.
     *
     * @param list<array{type: string, id: int}> $targets Цели.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если поле.
     */
    public function targets(array $targets): array
    {
        $rows = [];
        foreach ($targets as $target) {
            $rows[] = [
                'target_type' => $target['type'],
                'target_id' => $target['id'],
                'reaction' => null,
                'block_item_inventory_id' => null,
                'block_item_profile_index' => null,
                'block_item_rule_code' => null,
            ];
        }

        return $rows;
    }

    /**
     * Строки целей.
     *
     * @param int $strikeId Удар.
     * @param list<array{type: string, id: int}> $targets Цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    public function addTargets(int $strikeId, array $targets): void
    {
        foreach ($targets as $target) {
            $this->actions->addTarget($strikeId, [
                'target_type' => $target['type'],
                'target_id' => $target['id'],
                'reaction' => null,
                'block_item_inventory_id' => null,
                'block_item_profile_index' => null,
                'block_item_rule_code' => null,
            ]);
        }
    }
}
