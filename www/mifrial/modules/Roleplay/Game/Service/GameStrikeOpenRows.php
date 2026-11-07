<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка действия и строки ударов открытого удара.
 */
final class GameStrikeOpenRows
{
    /**
     * Строка действия и цели.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $sessionId Сессия.
     * @param array<string, mixed> $choice Выбор.
     * @param string $kind Вид удара.
     *
     * @return array{action: array<string, mixed>, strikes: list<array<string, mixed>>} Строки.
     *
     * @throws GameInvalidException Если вид или профиль битый.
     */
    public function action(GameBattleRecord $battle, int $sessionId, array $choice, string $kind): array
    {
        $this->assertKind($kind, $choice);

        return [
            'action' => [
                'kind' => $kind,
                'battle_id' => $battle->getId(),
                'session_id' => $sessionId,
                'attacker_kind' => $choice['attacker']['type'],
                'attacker_id' => $choice['attacker']['id'],
                'action_rule_code' => $choice['actionRuleCode'],
                'open' => true,
            ],
            'strikes' => $this->strikesOf($choice, $kind),
        ];
    }

    /**
     * Строки ударов выбора.
     *
     * @param array<string, mixed> $choice Выбор.
     * @param string $kind Вид удара.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function strikesOf(array $choice, string $kind): array
    {
        if (is_array($choice['strikes'] ?? null) && $choice['strikes'] !== []) {
            return $this->strikeRows(
                $choice['strikes'],
                is_array($choice['maneuver'] ?? null) ? $choice['maneuver'] : [],
            );
        }

        $targets = $kind === 'strike' ? [$choice['defender']] : $choice['targets'];

        return $this->strikes($targets, $choice);
    }

    /**
     * Вид удара и профиль одиночной атаки.
     *
     * @param string $kind Вид удара.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид или профиль битый.
     */
    private function assertKind(string $kind, array $choice): void
    {
        if ($kind !== 'strike' && $kind !== 'wide_strike') {
            throw new GameInvalidException('Game strike kind is invalid');
        }

        if ($kind === 'strike' && !in_array($choice['profileType'], ['strike', 'throw', 'shoot'], true)) {
            throw new GameInvalidException('Game strike profile is invalid');
        }
    }

    /**
     * Строки ударов из разобранного выбора.
     *
     * @param list<array<string, mixed>> $strikes Строки.
     * @param array<string, mixed> $maneuver Манёвр строки.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function strikeRows(array $strikes, array $maneuver = []): array
    {
        $rows = [];
        foreach ($strikes as $strike) {
            $rows[] = [
                'instance_index' => $strike['instanceIndex'],
                'item_inventory_id' => $strike['itemInventoryId'],
                'item_rule_code' => $strike['itemRuleCode'],
                'profile_type' => $strike['profileType'],
                'profile_index' => $strike['profileIndex'],
                'target_type' => $strike['target']['type'],
                'target_id' => $strike['target']['id'],
                'conditions' => $strike['conditions'] ?? (new GameStrikeConditions())->defaults(),
                'maneuver' => $maneuver,
                'reaction' => null,
                'block_item_inventory_id' => null,
                'block_item_profile_index' => null,
                'block_item_rule_code' => null,
            ];
        }

        return $rows;
    }

    /**
     * Строки ударов.
     *
     * @param list<array{type: string, id: int}> $targets Цели.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function strikes(array $targets, array $choice): array
    {
        $instanceIndex = is_int($choice['instanceIndex'] ?? null) ? $choice['instanceIndex'] : 0;
        $rows = [];
        foreach ($targets as $target) {
            $rows[] = $this->strikeRow($target, $choice, $instanceIndex);
        }

        return $rows;
    }

    /**
     * Строка удара одной цели.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Выбор.
     * @param int $instanceIndex Экземпляр оружия.
     *
     * @return array<string, mixed> Строка.
     */
    private function strikeRow(array $target, array $choice, int $instanceIndex): array
    {
        $key = $target['type'] . ':' . $target['id'];
        $conditions = $choice['conditions'][$key] ?? $choice['conditions'] ?? null;

        return [
            'instance_index' => $instanceIndex,
            'item_inventory_id' => $choice['itemInventoryId'],
            'item_rule_code' => $choice['itemRuleCode'],
            'profile_type' => $choice['profileType'],
            'profile_index' => $choice['profileIndex'],
            'target_type' => $target['type'],
            'target_id' => $target['id'],
            'conditions' => is_array($conditions)
                ? $conditions
                : (new GameStrikeConditions())->defaults(),
            'maneuver' => is_array($choice['maneuver'] ?? null) ? $choice['maneuver'] : [],
            'action_modifiers' => is_array($choice['modifiers'] ?? null)
                ? array_values($choice['modifiers'])
                : [],
            'token_spend' => is_array($choice['tokenSpendInfo'] ?? null)
                ? $choice['tokenSpendInfo']
                : [],
            'reaction' => null,
            'block_item_inventory_id' => null,
            'block_item_profile_index' => null,
            'block_item_rule_code' => null,
        ];
    }
}
