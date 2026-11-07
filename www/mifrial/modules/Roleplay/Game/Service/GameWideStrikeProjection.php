<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Открытый широкий удар боя для текущего читателя.
 */
final class GameWideStrikeProjection
{
    /**
     * Создаёт чтение.
     *
     * @param GameActionRepository $actions Действия боя.
     * @param GameStrikeAccess $access Права удара.
     * @param GameNpcProjection $npcs NPC игры.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $actions,
        private readonly GameStrikeAccess $access,
        private readonly GameNpcProjection $npcs,
    ) {
    }

    /**
     * Открытый широкий удар боя.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $battleId Бой.
     *
     * @return array<string, mixed>|null JSON или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function of(GameRecord $game, int $actorUserId, bool $editAll, int $battleId): ?array
    {
        $row = $this->actions->findOpenByBattle($battleId, 'wide_strike');
        $strike = $this->strikeOf($row);
        if ($strike === null) {
            return null;
        }

        $npcs = $this->npcs->index($game->getId());
        $attacker = $strike['attacker'];
        $admitted = $this->npcs->admitted($game, $npcs[$attacker['id']] ?? null, $actorUserId);
        if ($attacker['type'] === 'npc' && !$admitted) {
            return null;
        }

        $strikes = $this->actions->getStrikes($strike['id']);
        $first = $strikes[0] ?? [];

        return [
            'id' => $strike['id'],
            'attacker' => $attacker,
            'actionRuleCode' => $this->stringOr($strike['row'], 'action_rule_code'),
            'itemInventoryId' => $this->intOr($first, 'item_inventory_id'),
            'itemRuleCode' => $this->stringOr($first, 'item_rule_code'),
            'profileType' => $this->stringOr($first, 'profile_type'),
            'profileIndex' => $this->intOr($first, 'profile_index'),
            'targets' => $this->targets($strikes, $game, $actorUserId, $editAll, $npcs),
        ];
    }

    /**
     * Валидное открытое действие или null.
     *
     * @param array<string, mixed>|null $row Строка.
     *
     * @return array{id: int, attacker: array{type: string, id: int}, row: array<string, mixed>}|null Действие.
     */
    private function strikeOf(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $id = $row['id'] ?? null;
        $kind = $row['attacker_kind'] ?? null;
        $attackerId = $row['attacker_id'] ?? null;
        if (!is_int($id) || !is_string($kind) || !is_int($attackerId)) {
            return null;
        }

        return ['id' => $id, 'attacker' => ['type' => $kind, 'id' => $attackerId], 'row' => $row];
    }

    /**
     * Цели открытого удара.
     *
     * @param list<array<string, mixed>> $rows Строки ударов.
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param array<int, GameNpcRecord> $npcs NPC игры.
     *
     * @return list<array<string, mixed>> Цели.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function targets(array $rows, GameRecord $game, int $actorUserId, bool $editAll, array $npcs): array
    {
        $writer = $this->access->isWriter($game, $actorUserId, $editAll);
        $targets = [];
        foreach ($rows as $row) {
            $target = $this->target($row, $game, $actorUserId, $editAll, $writer, $npcs);
            if ($target !== null) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * Одна цель открытого удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $writer Писатель.
     * @param array<int, GameNpcRecord> $npcs NPC игры.
     *
     * @return array<string, mixed>|null Цель или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function target(
        array $row,
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $writer,
        array $npcs,
    ): ?array {
        $kind = $row['target_type'] ?? null;
        $id = $row['target_id'] ?? null;
        if (!is_string($kind) || !is_int($id) || !$this->admitted($kind, $id, $game, $actorUserId, $npcs)) {
            return null;
        }

        $target = $this->baseTarget($row, $kind, $id);
        $reaction = $row['reaction'] ?? null;
        $canRead = $writer || $this->access->canAnswer($game, $actorUserId, $editAll, ['type' => $kind, 'id' => $id]);
        if (is_string($reaction) && $canRead) {
            $target += $this->reactionFields($row, $reaction);
        }

        return $target;
    }

    /**
     * Цель без полей ответа.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $kind Вид участника.
     * @param int $id Id участника.
     *
     * @return array<string, mixed> Цель.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function baseTarget(array $row, string $kind, int $id): array
    {
        $reaction = $row['reaction'] ?? null;
        $target = [
            'type' => $kind,
            'id' => $id,
            'answered' => is_string($reaction),
            'conditions' => GameStrikeTargetRow::conditions($row),
            'supporters' => GameStrikeTargetRow::jsonRows($row, 'supporters'),
            'coverers' => GameStrikeTargetRow::jsonRows($row, 'coverers'),
        ];
        $awaiting = GameStrikeTargetRow::awaiting($row);
        if ($awaiting !== []) {
            $target['awaiting'] = $awaiting;
        }

        return $target;
    }

    /**
     * Поля ответа цели.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $reaction Ответ.
     *
     * @return array<string, mixed> Поля.
     */
    private function reactionFields(array $row, string $reaction): array
    {
        $blockId = $row['block_item_inventory_id'] ?? null;
        $blockIndex = $row['block_item_profile_index'] ?? null;
        $blockCode = $row['block_item_rule_code'] ?? null;

        return [
            'reaction' => $reaction,
            'blockItemInventoryId' => is_int($blockId) ? $blockId : null,
            'blockItemProfileIndex' => is_int($blockIndex) ? $blockIndex : null,
            'blockItemRuleCode' => is_string($blockCode) ? $blockCode : null,
        ];
    }

    /**
     * Цель видна читателю.
     *
     * @param string $kind Вид участника.
     * @param int $id Id участника.
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<int, GameNpcRecord> $npcs NPC игры.
     *
     * @return bool true, если цель видна.
     */
    private function admitted(string $kind, int $id, GameRecord $game, int $actorUserId, array $npcs): bool
    {
        if ($kind === 'npc') {
            return $this->npcs->admitted($game, $npcs[$id] ?? null, $actorUserId);
        }

        return $kind === 'character';
    }

    /**
     * Целое поле строки удара.
     *
     * @param array<string, mixed> $row Колонки.
     * @param string $key Ключ.
     *
     * @return int Значение.
     */
    private function intOr(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return is_int($value) ? $value : 0;
    }

    /**
     * Строковое поле строки удара.
     *
     * @param array<string, mixed> $row Колонки.
     * @param string $key Ключ.
     *
     * @return string Значение.
     */
    private function stringOr(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
