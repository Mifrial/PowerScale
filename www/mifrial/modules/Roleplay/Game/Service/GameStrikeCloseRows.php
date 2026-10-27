<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Строки, цели и права закрытия удара.
 */
final class GameStrikeCloseRows
{
    /**
     * Создаёт чтение строк удара.
     *
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeAccess $access Права ответа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeAccess $access,
    ) {
    }

    /**
     * Открытый одиночный удар с проверкой актора.
     *
     * @param int $battleId Бой.
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return array<string, mixed> Строка.
     */
    public function openSingle(int $battleId, GameRecord $game, int $actorUserId, bool $editAll): array
    {
        $open = $this->actions->findOpenWithStrikes($battleId, 'strike');
        if ($open !== null) {
            $this->access->assertTarget($game, $actorUserId, $editAll, $this->defenderOfRow($open));
            $strikeId = $open['id'] ?? null;
            if (!is_int($strikeId)) {
                throw new GameInvalidException('Game strike row is invalid');
            }

            $locked = $this->actions->lockById($strikeId);
            if (
                !is_array($locked)
                || ($locked['open'] ?? null) !== true
                || ($locked['kind'] ?? null) !== 'strike'
            ) {
                throw new GameInvalidException('Game strike is not open');
            }

            return $open;
        }

        if ($this->access->isWriter($game, $actorUserId, $editAll)) {
            throw new GameInvalidException('Game strike is not open');
        }

        $latest = $this->actions->findLatestWithStrikes($battleId, 'strike');
        if ($latest === null) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $this->access->assertTarget($game, $actorUserId, $editAll, $this->defenderOfRow($latest));
        throw new GameInvalidException('Game strike is not open');
    }

    /**
     * Защитник из строки с ударами.
     *
     * @param array<string, mixed> $open Строка.
     *
     * @return array{type: string, id: int} Пара.
     */
    private function defenderOfRow(array $open): array
    {
        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        $row = is_array($strikes[0] ?? null) ? $strikes[0] : null;
        if ($row === null) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->defender($row);
    }

    /**
     * Бой этой сессии с ожидаемой версией.
     *
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия.
     *
     * @return GameBattleRecord Строка.
     */
    public function battleOf(int $sessionId, int $battleId, int $expectedBattleVersion): GameBattleRecord
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        if ($battle->getStateVersion() !== $expectedBattleVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        return $battle;
    }

    /**
     * Открытый удар вида.
     *
     * @param int $battleId Бой.
     * @param string $kind Вид.
     *
     * @return array<string, mixed> Строка.
     */
    public function requireOpen(int $battleId, string $kind): array
    {
        $open = $this->actions->findOpenByBattle($battleId, $kind);
        $strikeId = is_array($open) ? ($open['id'] ?? null) : null;
        if (!is_array($open) || !is_int($strikeId)) {
            $message = $kind === 'wide_strike' ? 'Game wide strike is not open' : 'Game strike is not open';
            throw new GameInvalidException($message);
        }

        $locked = $this->actions->lockById($strikeId);
        if (
            !is_array($locked)
            || ($locked['open'] ?? null) !== true
            || ($locked['kind'] ?? null) !== $kind
        ) {
            $message = $kind === 'wide_strike' ? 'Game wide strike is not open' : 'Game strike is not open';
            throw new GameInvalidException($message);
        }

        return $this->withFirstStrike($locked);
    }

    /**
     * Действие с полями первого удара.
     *
     * @param array<string, mixed> $open Колонки действия.
     *
     * @return array<string, mixed> Колонки действия и `strikes`.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function withFirstStrike(array $open): array
    {
        $strikeId = $open['id'] ?? null;
        $strikes = is_int($strikeId) ? $this->actions->getStrikes($strikeId) : [];
        $first = $strikes[0] ?? [];

        return array_merge($open, [
            'item_inventory_id' => $first['item_inventory_id'] ?? null,
            'item_rule_code' => $first['item_rule_code'] ?? null,
            'profile_type' => $first['profile_type'] ?? null,
            'profile_index' => $first['profile_index'] ?? null,
            'instance_index' => $first['instance_index'] ?? null,
            'strikes' => $strikes,
        ]);
    }

    /**
     * Цели без ответа.
     *
     * @param list<array<string, mixed>> $rows Цели.
     *
     * @return list<array{type: string, id: int}> Ожидание.
     */
    public function pendingTargets(array $rows): array
    {
        $pending = [];
        foreach ($rows as $row) {
            if (($row['reaction'] ?? null) !== null) {
                continue;
            }

            $pending[] = $this->rowTarget($row);
        }

        return $pending;
    }

    /**
     * Сохранённый ответ строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array<string, mixed> Ответ.
     */
    public function answerOfRow(array $row): array
    {
        $reaction = $row['reaction'] ?? null;
        $expectedSheetVersion = $row['expected_sheet_version'] ?? null;
        $inventoryId = $row['block_item_inventory_id'] ?? null;
        $profileIndex = $row['block_item_profile_index'] ?? null;
        $ruleCode = $row['block_item_rule_code'] ?? null;
        if (
            !is_string($reaction)
            || !is_int($expectedSheetVersion)
            || ($inventoryId !== null && !is_int($inventoryId))
            || ($profileIndex !== null && !is_int($profileIndex))
            || ($ruleCode !== null && !is_string($ruleCode))
        ) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return [
            'reaction' => $reaction,
            'blockItemInventoryId' => $inventoryId,
            'blockItemProfileIndex' => $profileIndex,
            'blockItemRuleCode' => $ruleCode,
            'expectedSheetVersion' => $expectedSheetVersion,
            'conditions' => GameStrikeTargetRow::conditions($row),
        ];
    }

    /**
     * Пара цели из строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array{type: string, id: int} Пара.
     */
    public function rowTarget(array $row): array
    {
        $kind = $row['target_type'] ?? null;
        $targetId = $row['target_id'] ?? null;
        if (!is_string($kind) || !is_int($targetId)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return ['type' => $kind, 'id' => $targetId];
    }

    /**
     * Ключ цели.
     *
     * @param array{type: string, id: int} $target Цель.
     *
     * @return string Ключ.
     */
    public function targetKey(array $target): string
    {
        return $target['type'] . ':' . $target['id'];
    }

    /**
     * Строка удара с подменой защитника.
     *
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Строка.
     */
    public function targetOpen(array $open, array $target): array
    {
        return array_merge($open, [
            'defender_kind' => $target['type'],
            'defender_id' => $target['id'],
        ]);
    }

    /**
     * Защитник всё ещё в составе.
     *
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return void
     */
    public function assertDefender(int $battleId, array $target): void
    {
        $this->assertKind($target['type']);
        if (!in_array($target, $this->battles->findParticipants($battleId), true)) {
            throw new GameNotFoundException('Game battle participant was not found');
        }
    }

    /**
     * Пара защитника из строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array{type: string, id: int} Пара.
     */
    public function defender(array $row): array
    {
        $kind = $row['target_type'] ?? null;
        $defenderId = $row['target_id'] ?? null;
        if (!is_string($kind) || !is_int($defenderId)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return ['type' => $kind, 'id' => $defenderId];
    }

    /**
     * Вид участника.
     *
     * @param string $kind Вид.
     *
     * @return void
     */
    private function assertKind(string $kind): void
    {
        if ($kind !== 'character' && $kind !== 'npc') {
            throw new GameInvalidException('Game wide strike participant is invalid');
        }
    }
}
