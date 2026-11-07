<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;

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
            'marks' => $this->marksOf($battle->getMarks()),
        ];
    }

    /**
     * Метки боя с подписью источника.
     *
     * @param array<int, array<string, mixed>> $marks Метки.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function marksOf(array $marks): array
    {
        $rows = [];
        foreach ($marks as $mark) {
            $sourceCode = is_string($mark['source_code'] ?? null) ? $mark['source_code'] : null;
            $mark['source_label'] = AdvantageSourceLabels::getLabel($sourceCode);
            $rows[] = $mark;
        }

        return $rows;
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

        return $this->strikeFields($row, $this->lastStrike($row));
    }

    /**
     * Последняя цель, ждущая реакции.
     *
     * @param array<string, mixed> $row Колонки.
     *
     * @return array<string, mixed> Цель.
     */
    private function lastStrike(array $row): array
    {
        $strikes = is_array($row['strikes'] ?? null) ? $row['strikes'] : [];
        $target = [];
        foreach ($strikes as $strike) {
            if (!is_array($strike)) {
                continue;
            }

            $target = $strike;
            if (($strike['reaction'] ?? null) === null) {
                break;
            }
        }

        return $target;
    }

    /**
     * Плоские поля открытого удара.
     *
     * @param array<string, mixed> $row Колонки.
     * @param array<string, mixed> $target Цель.
     *
     * @return array<string, mixed> JSON.
     */
    private function strikeFields(array $row, array $target): array
    {
        return [
            'strikeId' => $this->intField($target, 'id'),
            'attackerKind' => $this->stringField($row, 'attacker_kind'),
            'attackerId' => $this->intField($row, 'attacker_id'),
            'defenderKind' => $this->stringField($target, 'target_type'),
            'defenderId' => $this->intField($target, 'target_id'),
            'actionRuleCode' => $this->stringField($row, 'action_rule_code'),
            'itemInventoryId' => $this->intField($target, 'item_inventory_id'),
            'itemRuleCode' => $this->stringField($target, 'item_rule_code'),
            'profileType' => $this->stringField($target, 'profile_type'),
            'profileIndex' => $this->intField($target, 'profile_index'),
            'conditions' => GameStrikeTargetRow::conditions($target),
            'supporters' => GameStrikeTargetRow::jsonRows($target, 'supporters'),
            'coverers' => GameStrikeTargetRow::jsonRows($target, 'coverers'),
            'awaiting' => GameStrikeTargetRow::awaiting($target),
            'maneuver' => $this->maneuverOf($target),
        ];
    }

    /**
     * Раскрытый манёвр открытого удара.
     *
     * @param array<string, mixed> $row Колонки.
     *
     * @return array<string, mixed>|null Описание или null.
     */
    private function maneuverOf(array $row): ?array
    {
        $raw = $this->resolutionOf($row);
        if (!is_array($raw) || !is_array($raw['maneuver'] ?? null)) {
            return null;
        }

        $maneuver = $raw['maneuver'];

        return [
            'ruleCode' => is_string($maneuver['ruleCode'] ?? null) ? $maneuver['ruleCode'] : '',
            'phase' => is_string($raw['phase'] ?? null) ? $raw['phase'] : '',
            'attacker' => $raw['attacker'] ?? null,
            'targets' => $this->maneuverTargets($raw['targets'] ?? null),
        ];
    }

    /**
     * Цели раскрытого манёвра.
     *
     * @param mixed $entries JSON.
     *
     * @return array<string|int, array<string, mixed>> Цели.
     */
    private function maneuverTargets(mixed $entries): array
    {
        $targets = [];
        foreach (is_array($entries) ? $entries : [] as $key => $target) {
            if (!is_array($target)) {
                continue;
            }

            $targets[$key] = [
                'actor' => $target['actor'] ?? null,
                'check' => $target['check'] ?? null,
                'roll' => $target['roll'] ?? null,
                'won' => $target['won'] ?? null,
                'decision' => $target['decision'] ?? null,
            ];
        }

        return $targets;
    }

    /**
     * Разобранный resolution строки.
     *
     * @param array<string, mixed> $row Колонки.
     *
     * @return mixed Значение.
     */
    private function resolutionOf(array $row): mixed
    {
        $raw = $row['resolution'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return $raw;
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
     * Строковое поле строки удара.
     *
     * @param array<string, mixed> $row Колонки.
     * @param string $key Ключ.
     *
     * @return string Значение.
     */
    private function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
