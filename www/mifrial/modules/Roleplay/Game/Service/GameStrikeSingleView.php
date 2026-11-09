<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Общие данные одиночного удара для чата.
 */
final class GameStrikeSingleView
{
    /**
     * Создаёт подготовку данных.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeRules $rules Правила.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Подписи атакующего, цели, оружия и толчка.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $result Итог.
     * @param array<string, mixed>|null $cover Прикрытие.
     * @param int $strikeId Удар.
     *
     * @return array{attacker: string, defender: string, victim: string, weapon: string, kind: string, typeBit: string, actionCode: string, push: array<string, mixed>|null, pushRating: int|null, slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, threadId: string, parentId: string|null, attackerSpeaker: array<string, mixed>, defenderSpeaker: array<string, mixed>} Подписи.
     */
    public function header(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        array $result,
        ?array $cover,
        int $strikeId,
    ): array {
        $thread = $this->thread($battle, $strikeId, $open);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $push = $this->push($result);

        return $this->tokens($game, $open, $cover, $slice) + [
            'kind' => $this->kind($open),
            'actionCode' => is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
            'push' => $push,
            'pushRating' => $push !== null && is_int($push['rating'] ?? null) ? $push['rating'] : null,
            'slice' => $slice,
            'threadId' => $thread['threadId'],
            'parentId' => $thread['parentId'],
        ];
    }

    /**
     * Тред удара и его родитель.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Удар.
     * @param array<string, mixed>|null $open Открытый удар.
     *
     * @return array{threadId: string, parentId: string|null} Тред.
     */
    public function thread(GameBattleRecord $battle, int $strikeId, ?array $open = null): array
    {
        $threadId = 'attack-' . $strikeId;
        $parentId = $this->isSpellTouch($open)
            ? GameCombatThread::spellThreadId($strikeId)
            : GameCombatThread::buildAttack($battle, $threadId)['parentId'];

        return ['threadId' => $threadId, 'parentId' => $parentId];
    }

    /**
     * Удар — касание заклинания.
     *
     * @param array<string, mixed>|null $open Открытый удар.
     *
     * @return bool Истина, если удар несёт заклинание.
     */
    private function isSpellTouch(?array $open): bool
    {
        $spell = $open['spell_cast'] ?? null;

        return is_array($spell) && $spell !== [];
    }

    /**
     * Токен цели из строки удара.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return string Токен.
     */
    public function defenderToken(array $open): string
    {
        return $this->chat->getEntityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
    }

    /**
     * Спикер атакующего.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return array<string, mixed> Спикер.
     */
    public function attackerSpeaker(array $open): array
    {
        return $this->chat->getSpeakerOf($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0);
    }

    /**
     * Спикер цели.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return array<string, mixed> Спикер.
     */
    public function defenderSpeaker(array $open): array
    {
        return $this->chat->getSpeakerOf($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
    }

    /**
     * Победившая и провалившиеся попытки прикрытия.
     *
     * @param array<string, mixed>|null $cover Прикрытие.
     *
     * @return array{winner: array<string, mixed>|null, failed: list<array<string, mixed>>} Части.
     */
    public function cover(?array $cover): array
    {
        $winner = null;
        $failed = [];
        foreach (is_array($cover['attempts'] ?? null) ? $cover['attempts'] : [] as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            if (($attempt['winner'] ?? false) === true) {
                $winner = $attempt;
                continue;
            }

            $failed[] = $attempt;
        }

        return ['winner' => $winner, 'failed' => $failed];
    }

    /**
     * Токены участников и оружия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed>|null $cover Прикрытие.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{attacker: string, defender: string, victim: string, weapon: string, typeBit: string, attackerSpeaker: array<string, mixed>, defenderSpeaker: array<string, mixed>} Токены.
     */
    private function tokens(GameRecord $game, array $open, ?array $cover, CharacterRuleSlice $slice): array
    {
        $defender = $this->defenderToken($open);

        return [
            'attacker' => $this->chat->getEntityToken($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0),
            'defender' => $defender,
            'victim' => $cover !== null
                ? $this->chat->getEntityToken($cover['target']['type'], $cover['target']['id'])
                : $defender,
            'weapon' => is_string($open['item_rule_code'] ?? null)
                ? '[[rule:' . $open['item_rule_code'] . ']]'
                : 'оружием',
            'typeBit' => $this->chat->getDamageTypeBit(
                $game,
                $open['item_rule_code'] ?? null,
                $open['profile_type'] ?? null,
                $open['profile_index'] ?? null,
                $slice,
            ),
            'attackerSpeaker' => $this->attackerSpeaker($open),
            'defenderSpeaker' => $this->defenderSpeaker($open),
        ];
    }

    /**
     * Данные толчка из итога.
     *
     * @param array<string, mixed> $result Итог.
     *
     * @return array<string, mixed>|null Толчок.
     */
    private function push(array $result): ?array
    {
        return is_array($result['push'] ?? null) ? $result['push'] : null;
    }

    /**
     * Вид удара по профилю.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return string Вид.
     */
    private function kind(array $open): string
    {
        return match ($open['profile_type'] ?? 'strike') {
            'throw' => 'бросок',
            'shoot' => 'выстрел',
            default => 'удар',
        };
    }
}
