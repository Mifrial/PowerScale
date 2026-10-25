<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Чат широкого удара.
 */
final class GameStrikeWideChat
{
    /**
     * Создаёт чат.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Публикация.
     * @param GameStrikeSpend $spend Стоимость действия.
     * @param GameStrikeRules $rules Правила.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Чат широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<array<string, mixed>> $results Итоги.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $open Открытый удар.
     * @param GameBattleRecord $battle Бой.
     * @param CharacterRuleSlice $slice Срез.
     * @param int $actorUserId Актор.
     *
     * @return void
     */
    public function post(
        GameRecord $game,
        array $rows,
        array $choices,
        array $results,
        array $attackerRoll,
        array $open,
        GameBattleRecord $battle,
        CharacterRuleSlice $slice,
        int $actorUserId,
    ): void {
        $shown = [];
        foreach ($results as $index => $record) {
            if (($record['chat'] ?? false) !== true) {
                continue;
            }

            $shown[] = ['row' => $rows[$index], 'choice' => $choices[$index], 'record' => $record];
        }

        if ($shown === [] || !isset($rows[0])) {
            return;
        }

        $row = $open !== [] ? $open : $rows[0];
        $attacker = $this->chat->getEntityToken($row['attacker_kind'] ?? 'character', $row['attacker_id'] ?? 0);
        $attackerName = $this->chat->getEntityName(
            is_string($row['attacker_kind'] ?? null) ? $row['attacker_kind'] : 'character',
            is_int($row['attacker_id'] ?? null) ? $row['attacker_id'] : 0,
        );
        $actionCode = is_string($row['action_rule_code'] ?? null) ? $row['action_rule_code'] : '';
        $action = $actionCode !== '' ? '[[rule:' . $actionCode . ']]' : 'действие';
        $profileType = is_string($row['profile_type'] ?? null) ? $row['profile_type'] : 'strike';
        $itemRuleCode = is_string($row['item_rule_code'] ?? null) ? $row['item_rule_code'] : '';
        $profileIndex = is_int($row['profile_index'] ?? null) ? $row['profile_index'] : 0;
        $kind = match ($profileType) {
            'throw' => 'бросок',
            'shoot' => 'выстрел',
            default => 'удар',
        };
        $typeBit = $this->chat->getDamageTypeBit($game, $itemRuleCode, $profileType, $profileIndex, $slice);
        $targets = [];
        $reactions = [];
        foreach ($shown as $item) {
            $target = $item['record']['target'] ?? $this->defender($item['row']);
            $name = $this->chat->getEntityToken($target['type'], $target['id']);
            $targets[] = $name;
            $reaction = $item['choice']['reaction'] ?? 'ignore';
            $reactionCode = $reaction === 'ignore'
                ? null
                : $this->rules->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $reaction);
            $reactions[] = $this->chat->getReactionBit(
                $name,
                $reaction,
                $reactionCode,
                $this->spend->getActionAp($slice, $reactionCode ?? ''),
            );
        }

        $narrative = $attacker . ' наносит ' . $typeBit . ' ' . $kind . ' оружием [[rule:' . $itemRuleCode . ']] по '
            . $this->chat->getJoinNames($targets) . '. ' . implode(', ', $reactions) . '!';
        $threadId = 'attack-wide-' . ($row['id'] ?? 0);
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $speaker = $this->chat->getSpeakerOf($row['attacker_kind'] ?? 'character', $row['attacker_id'] ?? 0);
        $attackCard = $attackerRoll;
        $attackCard['label'] = 'Проверка на попадание · ' . $attackerName;
        $attackCard['min_success_size'] = -1;
        $rolls = [$attackCard];
        foreach ($shown as $item) {
            if (!is_array($item['record']['defenderRoll'] ?? null)) {
                continue;
            }

            $target = is_array($item['record']['target'] ?? null)
                ? $item['record']['target']
                : ['type' => 'character', 'id' => 0];
            $defenderName = $this->chat->getEntityName(
                is_string($target['type'] ?? null) ? $target['type'] : 'character',
                is_int($target['id'] ?? null) ? $target['id'] : 0,
            );
            $reaction = $item['choice']['reaction'] ?? 'ignore';
            $card = $item['record']['defenderRoll'];
            $card['label'] = ($reaction === 'block' ? 'Блок' : 'Уклонение')
                . ' · ' . $defenderName . ' против ' . $attackerName;
            $card['min_success_size'] = -1;
            $rolls[] = $card;
        }

        $actionAp = $this->spend->getActionAp($slice, $actionCode);
        $this->consequences->postLines($game, $actorUserId, $threadId, [
            $attacker . ' совершает действие ' . $action . ' за ' . $actionAp . 'ОД.',
            $narrative,
        ], $speaker, $parentId);
        $this->consequences->postRolls($game, $actorUserId, $threadId, $rolls, $speaker, $parentId);
        foreach ($shown as $item) {
            $this->outcome(
                $game,
                $actorUserId,
                $threadId,
                $attacker,
                $item['record'],
                $typeBit,
                $speaker,
                $parentId,
            );
        }
    }

    /**
     * Итог одной цели в чат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Тред.
     * @param string $attacker Атакующий.
     * @param array<string, mixed> $record Итог.
     * @param string $typeBit Тип урона.
     * @param array<string, mixed> $speaker Спикер.
     * @param string|null $parentId Родитель.
     *
     * @return void
     */
    private function outcome(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        string $attacker,
        array $record,
        string $typeBit,
        array $speaker,
        ?string $parentId = null,
    ): void {
        $target = is_array($record['target'] ?? null) ? $record['target'] : ['type' => 'character', 'id' => 0];
        $defender = $this->chat->getEntityToken($target['type'] ?? 'character', $target['id'] ?? 0);
        $success = is_int($record['success'] ?? null) ? $record['success'] : 0;
        $exhaustion = $record['exhaustionDelta'] ?? 0;
        $wound = $record['wound'] ?? 0;
        $will = isset($record['will']) ? [
            'roll' => $record['will'],
            'text' => $this->chat->getExhaustionText(
                $defender,
                $record['will']['rating'] ?? 0,
                $record['willOutcome'] ?? 'clear',
            ),
        ] : null;
        $maim = isset($record['maim']) ? $this->chat->getMaimPayload($defender, $record['maim'], $typeBit) : null;
        $bits = [$exhaustion . ' истощения'];
        if ($wound > 0) {
            $bits[] = $wound . ' рану';
        }

        $this->consequences->postOutcome(
            $game,
            $actorUserId,
            $threadId,
            $success > 0
                ? $attacker . ' попадает по ' . $defender . ' с ' . $success
                    . ' РУ и наносит ' . implode(' и ', $bits) . '!'
                : $attacker . ' промахивается по ' . $defender . '!',
            [
                'raw' => $this->integerOf($record['injury'] ?? null),
                'damage' => is_array($record['damage'] ?? null) ? $record['damage'] : ['base' => 0, 'size' => 0],
                'damageTypeName' => $typeBit,
                'resistance' => is_array($record['resistance'] ?? null) ? ($record['resistance']['base'] ?? 0) : 0,
                'injury' => $this->pairOf($record['injury'] ?? null),
                'dodgeSoak' => $this->pairOf($record['S'] ?? null),
                'durabilityShave' => is_int($record['durabilityShave'] ?? null) ? $record['durabilityShave'] : 0,
                'endurance' => is_array($record['endurance'] ?? null)
                    ? $record['endurance']
                    : ['base' => 0, 'size' => 0],
                'defenseIgnored' => false,
                'attackSrLabel' => (string) $success,
                'appliedSr' => $success,
                'srCap' => null,
                'heading' => null,
                'stun' => null,
                'shock' => null,
                'exhaustion' => $exhaustion,
                'remainingHpDamage' => $this->pairOf($record['damageRemainder'] ?? null),
                'wound' => $wound,
                'knockout' => ($record['unconscious'] ?? false) === true,
                'cuttingWound' => null,
                'layers' => [],
            ],
            $will,
            $maim,
            $speaker,
            $parentId,
        );
    }

    /**
     * Пара защитника из строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array{type: string, id: int} Пара.
     */
    private function defender(array $row): array
    {
        $kind = $row['target_type'] ?? null;
        $defenderId = $row['target_id'] ?? null;
        if (!is_string($kind) || !is_int($defenderId)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return ['type' => $kind, 'id' => $defenderId];
    }

    /**
     * Пара base/size или ноль.
     *
     * @param mixed $pair Пара base/size.
     *
     * @return array{base: int, size: int} Пара.
     */
    private function pairOf(mixed $pair): array
    {
        if (!is_array($pair) || !is_int($pair['base'] ?? null) || !is_int($pair['size'] ?? null)) {
            return ['base' => 0, 'size' => 0];
        }

        return ['base' => $pair['base'], 'size' => $pair['size']];
    }

    /**
     * Целое значение пары или 0.
     *
     * @param mixed $pair Пара base/size.
     *
     * @return int Число.
     */
    private function integerOf(mixed $pair): int
    {
        if (!is_array($pair) || !is_int($pair['base'] ?? null) || !is_int($pair['size'] ?? null)) {
            return 0;
        }

        return (new DimensionalNumber($pair['base'], $pair['size']))->toInteger();
    }
}
