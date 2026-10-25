<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Чат одиночного удара.
 */
final class GameStrikeSingleChat
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
     * Чат одиночного удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param string $reaction Реакция.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function post(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        string $reaction,
        array $result,
    ): void {
        $threadId = 'attack-' . $strikeId;
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $defender = $this->chat->getEntityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        $attacker = $this->chat->getEntityToken($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0);
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $actionCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $action = $actionCode !== '' ? '[[rule:' . $actionCode . ']]' : 'действие';
        $actionAp = $this->spend->getActionAp($slice, $actionCode, true);
        $weapon = is_string($open['item_rule_code'] ?? null) ? '[[rule:' . $open['item_rule_code'] . ']]' : 'оружием';
        $kind = match ($open['profile_type'] ?? 'strike') {
            'throw' => 'бросок',
            'shoot' => 'выстрел',
            default => 'удар',
        };
        $typeBit = $this->chat->getDamageTypeBit(
            $game,
            $open['item_rule_code'] ?? null,
            $open['profile_type'] ?? null,
            $open['profile_index'] ?? null,
            $slice,
        );
        $narrative = $attacker . ' наносит ' . $typeBit . ' ' . $kind
            . ' оружием ' . $weapon . ' по ' . $defender . '.';
        if ($reaction === 'ignore') {
            $narrative .= ' Тот не реагирует.';
        } else {
            $reactionCode = $this->rules->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $reaction);
            $narrative .= ' ' . $this->chat->getReactionBit(
                'Тот',
                $reaction,
                $reactionCode,
                $this->spend->getActionAp($slice, $reactionCode ?? ''),
            ) . '!';
        }

        $exhaustion = $result['exhaustionDelta'] ?? 0;
        $wound = $result['wound'] ?? 0;
        $success = $result['success'] ?? 0;
        $bits = [$exhaustion . ' истощения'];
        if ($wound > 0) {
            $bits[] = $wound . ' рану';
        }

        $will = isset($result['will']) ? [
            'roll' => $result['will'],
            'text' => $this->chat->getExhaustionText(
                $defender,
                $result['will']['rating'] ?? 0,
                $result['willOutcome'] ?? 'clear',
            ),
        ] : null;
        $maim = isset($result['maim']) ? $this->chat->getMaimPayload($defender, $result['maim'], $typeBit) : null;
        $this->consequences->post(
            $game,
            $actorUserId,
            $threadId,
            true,
            $attacker . ' совершает действие ' . $action . ' за ' . $actionAp . 'ОД.',
            $narrative,
            $result['attackerRoll'],
            $success > 0
                ? $attacker . ' попадает по ' . $defender . ' с ' . $success
                    . ' РУ и наносит ' . implode(' и ', $bits) . '!'
                : $attacker . ' промахивается по ' . $defender . '!',
            [
                'raw' => $this->integerOf($result['injury'] ?? null),
                'damage' => is_array($result['damage'] ?? null) ? $result['damage'] : ['base' => 0, 'size' => 0],
                'damageTypeName' => $typeBit,
                'resistance' => is_array($result['resistance'] ?? null) ? ($result['resistance']['base'] ?? 0) : 0,
                'injury' => $this->pairOf($result['injury'] ?? null),
                'dodgeSoak' => $this->pairOf($result['S'] ?? null),
                'durabilityShave' => is_int($result['durabilityShave'] ?? null) ? $result['durabilityShave'] : 0,
                'endurance' => is_array($result['endurance'] ?? null)
                    ? $result['endurance']
                    : ['base' => 0, 'size' => 0],
                'defenseIgnored' => false,
                'attackSrLabel' => (string) $success,
                'appliedSr' => $success,
                'srCap' => null,
                'heading' => null,
                'stun' => null,
                'shock' => null,
                'exhaustion' => $exhaustion,
                'remainingHpDamage' => $this->pairOf($result['damageRemainder'] ?? null),
                'wound' => $wound,
                'knockout' => ($result['unconscious'] ?? false) === true,
                'cuttingWound' => null,
                'layers' => [],
            ],
            $will,
            $maim,
            $this->chat->getSpeakerOf($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0),
            is_array($result['defenderRoll'] ?? null) ? $result['defenderRoll'] : null,
            $parentId,
        );
    }

    /**
     * Строка урона дополнительного пакета.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param int $packet Номер пакета.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function postPacket(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        int $packet,
        array $result,
    ): void {
        $threadId = 'attack-' . $strikeId;
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $defender = $this->chat->getEntityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        $injury = $this->integerOf($result['injury'] ?? null);
        $this->consequences->postOutcome(
            $game,
            $actorUserId,
            $threadId,
            'Пакет ' . $packet . ': ' . $defender . ' получает ещё ' . $injury . ' повреждения.',
            [
                'raw' => $injury,
                'damage' => is_array($result['damage'] ?? null) ? $result['damage'] : ['base' => 0, 'size' => 0],
                'damageTypeName' => '',
                'resistance' => is_array($result['resistance'] ?? null) ? ($result['resistance']['base'] ?? 0) : 0,
                'injury' => $this->pairOf($result['injury'] ?? null),
                'dodgeSoak' => $this->pairOf($result['S'] ?? null),
                'durabilityShave' => is_int($result['durabilityShave'] ?? null) ? $result['durabilityShave'] : 0,
                'endurance' => is_array($result['endurance'] ?? null)
                    ? $result['endurance']
                    : ['base' => 0, 'size' => 0],
                'defenseIgnored' => false,
                'attackSrLabel' => '',
                'appliedSr' => is_int($result['success'] ?? null) ? $result['success'] : 0,
                'srCap' => null,
                'heading' => null,
                'stun' => null,
                'shock' => null,
                'exhaustion' => 0,
                'remainingHpDamage' => ['base' => 0, 'size' => 0],
                'wound' => 0,
                'knockout' => false,
                'cuttingWound' => null,
                'layers' => [],
            ],
            null,
            null,
            $this->chat->getSpeakerOf($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0),
            null,
            $parentId,
        );
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
