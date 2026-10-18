<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Плоский JSON игры. sessionRunning истинен, когда строка сессии есть.
 */
final class GameViewAssembler
{
    /**
     * Карточка списка.
     *
     * @param GameRecord $record Строка.
     * @param bool $sessionRunning Текущая сессия есть.
     *
     * @return array<string, mixed> JSON.
     */
    public function listItem(GameRecord $record, bool $sessionRunning): array
    {
        return [
            'id' => $record->getId(),
            'name' => $record->getName(),
            'shortDescription' => $record->getShortDescription(),
            'status' => $record->getStatus(),
            'sessionRunning' => $sessionRunning,
            'visibility' => $record->getVisibility(),
            'joinPolicy' => $record->getJoinPolicy(),
            'ownerId' => $record->getOwnerId(),
            'spaceId' => $record->getSpaceId(),
            'spaceCode' => $record->getSpaceCode(),
            'rulesRevision' => $record->getRulesRevision(),
            'gameChatId' => $record->getGameChatId(),
            'discussionChatId' => $record->getDiscussionChatId(),
        ];
    }

    /**
     * Карточка с описанием и потолками.
     *
     * @param GameRecord $record Строка.
     * @param bool $sessionRunning Текущая сессия есть.
     * @param GameBattleRecord|null $battle Открытый бой или null.
     * @param array<string, mixed>|null $openStrike Открытый удар 1→1 или null.
     *
     * @return array<string, mixed> JSON.
     */
    public function detail(
        GameRecord $record,
        bool $sessionRunning,
        ?GameBattleRecord $battle = null,
        ?array $openStrike = null,
    ): array {
        return $this->listItem($record, $sessionRunning) + [
            'description' => $record->getDescription(),
            'osPointsLimit' => $record->getOsPointsLimit(),
            'olPointsLimit' => $record->getOlPointsLimit(),
            'orPointsLimit' => $record->getOrPointsLimit(),
            'moneyLimit' => $record->getMoneyLimit(),
            'createdAt' => $record->getCreatedAt()->toUnix(),
            'updatedAt' => $record->getUpdatedAt()->toUnix(),
            'battle' => $this->battleOf($battle, $openStrike),
        ];
    }

    /**
     * Снимок открытого боя или null.
     *
     * @param GameBattleRecord|null $battle Строка.
     * @param array<string, mixed>|null $openStrike Открытый удар или null.
     *
     * @return array<string, mixed>|null JSON.
     */
    private function battleOf(?GameBattleRecord $battle, ?array $openStrike = null): ?array
    {
        if ($battle === null) {
            return null;
        }

        return [
            'battleId' => $battle->getId(),
            'version' => $battle->getStateVersion(),
            'order' => $battle->getTurnOrder(),
            'activeIndex' => $battle->getActiveIndex(),
            'round' => $battle->getRound(),
            'openStrike' => $this->openStrikeOf($openStrike),
            'pendingActionEffects' => $battle->getPendingActionEffects(),
        ];
    }

    /**
     * JSON открытого удара.
     *
     * @param array<string, mixed>|null $row Колонки.
     *
     * @return array<string, mixed>|null JSON.
     */
    private function openStrikeOf(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'attackerKind' => is_string($row['attacker_kind'] ?? null) ? $row['attacker_kind'] : '',
            'attackerId' => $this->intField($row, 'attacker_id'),
            'defenderKind' => is_string($row['defender_kind'] ?? null) ? $row['defender_kind'] : '',
            'defenderId' => $this->intField($row, 'defender_id'),
            'actionRuleCode' => is_string($row['action_rule_code'] ?? null) ? $row['action_rule_code'] : '',
            'itemInventoryId' => $this->intField($row, 'item_inventory_id'),
            'itemRuleCode' => is_string($row['item_rule_code'] ?? null) ? $row['item_rule_code'] : '',
            'profileType' => is_string($row['profile_type'] ?? null) ? $row['profile_type'] : '',
            'profileIndex' => $this->intField($row, 'profile_index'),
        ];
    }

    /**
     * Целое поле строки удара.
     *
     * @param array<string, mixed> $row Колонки.
     * @param string $key Ключ.
     *
     * @return int Значение.
     */
    private function intField(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Участник без имени и без списка прав.
     *
     * @param GameMemberRecord $record Строка.
     *
     * @return array<string, mixed> JSON.
     */
    public function member(GameMemberRecord $record): array
    {
        return [
            'userId' => $record->getUserId(),
            'role' => $record->getRole(),
        ];
    }
}
