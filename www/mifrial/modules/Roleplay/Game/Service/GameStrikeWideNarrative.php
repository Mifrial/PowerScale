<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Нарратив, реакции и карточки бросков широкого удара.
 */
final class GameStrikeWideNarrative
{
    /**
     * Создаёт нарратив.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSpend $spend Стоимость действия.
     * @param GameStrikeWideCoverText $coverText Тексты прикрытия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeWideCoverText $coverText,
    ) {
    }

    /**
     * Общие подписи удара: атакующий, действие, вид и тип урона.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $row Строка атакующего.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{attacker: string, attackerName: string, actionCode: string, action: string, kind: string, typeBit: string, itemRuleCode: string}
     */
    public function view(GameRecord $game, array $row, CharacterRuleSlice $slice): array
    {
        $kind = is_string($row['attacker_kind'] ?? null) ? $row['attacker_kind'] : 'character';
        $attackerId = is_int($row['attacker_id'] ?? null) ? $row['attacker_id'] : 0;
        $actionCode = is_string($row['action_rule_code'] ?? null) ? $row['action_rule_code'] : '';
        $profileType = is_string($row['profile_type'] ?? null) ? $row['profile_type'] : 'strike';
        $itemRuleCode = is_string($row['item_rule_code'] ?? null) ? $row['item_rule_code'] : '';
        $profileIndex = is_int($row['profile_index'] ?? null) ? $row['profile_index'] : 0;

        return [
            'attacker' => $this->chat->getEntityToken($kind, $attackerId),
            'attackerName' => $this->chat->getEntityName($kind, $attackerId),
            'actionCode' => $actionCode,
            'action' => $actionCode !== '' ? '[[rule:' . $actionCode . ']]' : 'действие',
            'kind' => match ($profileType) {
                'throw' => 'бросок',
                'shoot' => 'выстрел',
                default => 'удар',
            },
            'typeBit' => $this->chat->getDamageTypeBit($game, $itemRuleCode, $profileType, $profileIndex, $slice),
            'itemRuleCode' => $itemRuleCode,
        ];
    }

    /**
     * Цели, реакции, провалы прикрытия и нарратив.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $shown Показанные строки.
     * @param array<int, array<string, mixed>> $covers Прикрытия по строкам.
     * @param array{attacker: string, attackerName: string, actionCode: string, action: string, kind: string, typeBit: string, itemRuleCode: string} $view Подписи.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{targets: list<string>, reactions: list<string>, failed: list<string>, narrative: string} Части.
     */
    public function parts(
        GameRecord $game,
        array $shown,
        array $covers,
        array $view,
        CharacterRuleSlice $slice,
    ): array {
        $targets = [];
        $reactions = [];
        $failed = [];
        foreach ($shown as $item) {
            $rowId = is_int($item['row']['id'] ?? null) ? $item['row']['id'] : 0;
            $part = $this->part($game, $item, $covers[$rowId] ?? null, $view, $slice);
            $targets[] = $part['target'];
            if ($part['reaction'] !== null) {
                $reactions[] = $part['reaction'];
            }

            if ($part['failed'] !== null) {
                $failed[] = $part['failed'];
            }
        }

        return [
            'targets' => $targets,
            'reactions' => $reactions,
            'failed' => $failed,
            'narrative' => $this->sentence($view, $targets, $reactions, $failed),
        ];
    }

    /**
     * Карточки броска атаки и защит.
     *
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param list<array<string, mixed>> $shown Показанные строки.
     * @param array{attacker: string, attackerName: string, actionCode: string, action: string, kind: string, typeBit: string, itemRuleCode: string} $view Подписи.
     *
     * @return list<array<string, mixed>> Карточки.
     */
    public function rolls(array $attackerRoll, array $shown, array $view): array
    {
        $attackCard = $attackerRoll;
        $attackCard['label'] = 'Проверка на попадание · ' . $view['attackerName'];
        $attackCard['min_success_size'] = -1;
        $rolls = [$attackCard];
        foreach ($shown as $item) {
            $card = $this->defenseCard($item, $view['attackerName']);
            if ($card !== null) {
                $rolls[] = $card;
            }
        }

        return $rolls;
    }

    /**
     * Текст нарратива удара.
     *
     * @param array{attacker: string, attackerName: string, actionCode: string, action: string, kind: string, typeBit: string, itemRuleCode: string} $view Подписи.
     * @param list<string> $targets Цели.
     * @param list<string> $reactions Реакции.
     * @param list<string> $failed Провалы прикрытия.
     *
     * @return string Нарратив.
     */
    private function sentence(array $view, array $targets, array $reactions, array $failed): string
    {
        $narrative = $view['attacker'] . ' наносит ' . $view['typeBit'] . ' ' . $view['kind']
            . ' оружием [[rule:' . $view['itemRuleCode'] . ']] по ' . $this->chat->getJoinNames($targets) . '.';
        if ($reactions !== []) {
            $narrative .= ' ' . implode(', ', $reactions) . '!';
        }

        if ($failed !== []) {
            $narrative .= ' ' . implode('; ', $failed) . ' — прикрытие не удаётся.';
        }

        return $narrative;
    }

    /**
     * Цель и реакция одной показанной строки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $item Показанная строка.
     * @param array<string, mixed>|null $cover Прикрытие.
     * @param array{attacker: string, attackerName: string, actionCode: string, action: string, kind: string, typeBit: string, itemRuleCode: string} $view Подписи.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{target: string, reaction: string|null, failed: string|null} Часть.
     */
    private function part(GameRecord $game, array $item, ?array $cover, array $view, CharacterRuleSlice $slice): array
    {
        $target = is_array($cover['target'] ?? null) ? $cover['target'] : null;
        if ($target === null) {
            $target = is_array($item['record']['target'] ?? null)
                ? $item['record']['target']
                : GameStrikeTargetRow::defender($item['row']);
        }

        $name = $this->chat->getEntityToken(
            is_string($target['type'] ?? null) ? $target['type'] : 'character',
            is_int($target['id'] ?? null) ? $target['id'] : 0,
        );
        if ($cover !== null) {
            return [
                'target' => $name,
                'reaction' => $this->nonEmpty($this->coverText->bit($cover)),
                'failed' => $this->nonEmpty($this->coverText->attempts($cover)),
            ];
        }

        return ['target' => $name, 'reaction' => $this->reaction($game, $item, $name, $view, $slice), 'failed' => null];
    }

    /**
     * Реакция защиты строки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $item Показанная строка.
     * @param string $name Имя цели.
     * @param array{attacker: string, attackerName: string, actionCode: string, action: string, kind: string, typeBit: string, itemRuleCode: string} $view Подписи.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return string Текст реакции.
     */
    private function reaction(GameRecord $game, array $item, string $name, array $view, CharacterRuleSlice $slice): string
    {
        $reaction = is_string($item['choice']['reaction'] ?? null) ? $item['choice']['reaction'] : 'ignore';
        $reactionCode = $reaction === 'ignore'
            ? null
            : $this->rules->cards->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $reaction);
        $turn = ($item['choice']['conditions']['turn'] ?? false) === true;
        $turnCode = $turn
            ? $this->rules->cards->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), 'turn')
            : null;

        return $this->chat->getReactionBit(
            $name,
            $reaction,
            $reactionCode,
            $this->spend->getActionAp($slice, $reactionCode ?? ''),
            $turn,
            $this->spend->getActionAp($slice, $turnCode ?? ''),
        );
    }

    /**
     * Карточка броска защиты строки.
     *
     * @param array<string, mixed> $item Показанная строка.
     * @param string $attackerName Имя атакующего.
     *
     * @return array<string, mixed>|null Карточка.
     */
    private function defenseCard(array $item, string $attackerName): ?array
    {
        $roll = $item['record']['defenderRoll'] ?? null;
        if (!is_array($roll)) {
            return null;
        }

        $target = is_array($item['record']['target'] ?? null)
            ? $item['record']['target']
            : ['type' => 'character', 'id' => 0];
        $defenderName = $this->chat->getEntityName(
            is_string($target['type'] ?? null) ? $target['type'] : 'character',
            is_int($target['id'] ?? null) ? $target['id'] : 0,
        );
        $reaction = $item['choice']['reaction'] ?? 'ignore';
        $roll['label'] = ($reaction === 'block' ? 'Блок' : 'Уклонение')
            . ' · ' . $defenderName . ' против ' . $attackerName;
        $roll['min_success_size'] = -1;

        return $roll;
    }

    /**
     * Непустой текст или null.
     *
     * @param string $text Текст.
     *
     * @return string|null Текст или null.
     */
    private function nonEmpty(string $text): ?string
    {
        return $text === '' ? null : $text;
    }
}
