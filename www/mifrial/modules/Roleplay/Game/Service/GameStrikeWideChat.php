<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Чат широкого удара.
 */
final class GameStrikeWideChat
{
    /**
     * Создаёт чат.
     *
     * @param GameStrikeWideNarrative $narrative Нарратив и броски.
     * @param GameStrikeWideCoverText $coverText Тексты прикрытия.
     * @param GameStrikeWideOutcome $outcome Итоги целей.
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Публикация.
     * @param GameStrikeSpend $spend Стоимость действия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeWideNarrative $narrative,
        private readonly GameStrikeWideCoverText $coverText,
        private readonly GameStrikeWideOutcome $outcome,
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeSpend $spend,
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
     * @param array<int, array<string, mixed>> $covers Прикрытия по строкам.
     *
     * @return void
     */
    public function post(
        GameRecord $game, array $rows, array $choices, array $results, array $attackerRoll,
        array $open, GameBattleRecord $battle, CharacterRuleSlice $slice, int $actorUserId, array $covers = [],
    ): void {
        $shown = $this->shown($results, $rows, $choices);
        if ($shown === [] || !isset($rows[0])) {
            return;
        }

        $row = $open !== [] ? $open : $rows[0];
        $view = $this->narrative->view($game, $row, $slice);
        $parts = $this->narrative->parts($game, $shown, $covers, $view, $slice);
        [$threadId, $parentId, $speaker] = $this->thread($battle, $row);
        $actionAp = $this->spend->getActionAp($slice, $view['actionCode'], true);
        $this->consequences->chat->postLines($game, $actorUserId, $threadId, [
            $view['attacker'] . ' совершает действие ' . $view['action'] . ' за ' . $actionAp . 'ОД.',
            $parts['narrative'],
        ], $speaker, $parentId);
        $this->postCoverChecks($game, $actorUserId, $threadId, $slice, $shown, $covers, $speaker, $parentId);
        $this->consequences->chat->postRolls(
            $game,
            $actorUserId,
            $threadId,
            $this->narrative->rolls($attackerRoll, $shown, $view),
            $speaker,
            $parentId,
        );
        $this->postOutcomes($game, $actorUserId, $threadId, $view, $shown, $speaker, $parentId);
    }

    /**
     * Проверки прикрытий, если они были.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Тред.
     * @param CharacterRuleSlice $slice Срез.
     * @param list<array<string, mixed>> $shown Показанные строки.
     * @param array<int, array<string, mixed>> $covers Прикрытия.
     * @param array<string, mixed> $speaker Спикер.
     * @param string|null $parentId Родитель.
     *
     * @return void
     */
    private function postCoverChecks(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        CharacterRuleSlice $slice,
        array $shown,
        array $covers,
        array $speaker,
        ?string $parentId,
    ): void {
        foreach ($shown as $item) {
            $cover = $this->cover($item, $covers);
            if ($cover !== null) {
                $this->coverText->postChecks($game, $actorUserId, $threadId, $slice, $cover, $speaker, $parentId);
            }
        }
    }

    /**
     * Итоги всех показанных целей.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Тред.
     * @param array{attacker: string, attackerName: string, actionCode: string, action: string, kind: string, typeBit: string, itemRuleCode: string} $view Подписи.
     * @param list<array<string, mixed>> $shown Показанные строки.
     * @param array<string, mixed> $speaker Спикер.
     * @param string|null $parentId Родитель.
     *
     * @return void
     */
    private function postOutcomes(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        array $view,
        array $shown,
        array $speaker,
        ?string $parentId,
    ): void {
        foreach ($shown as $item) {
            $this->outcome->post(
                $game, $actorUserId, $threadId, $view['attacker'], $item['record'], $view['typeBit'], $speaker, $parentId,
            );
        }
    }

    /**
     * Показанные строки с итогом.
     *
     * @param list<array<string, mixed>> $results Итоги.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     *
     * @return list<array{row: array<string, mixed>, choice: array<string, mixed>, record: array<string, mixed>}> Строки.
     */
    private function shown(array $results, array $rows, array $choices): array
    {
        $shown = [];
        foreach ($results as $index => $record) {
            if (($record['chat'] ?? false) === true) {
                $shown[] = ['row' => $rows[$index], 'choice' => $choices[$index], 'record' => $record];
            }
        }

        return $shown;
    }

    /**
     * Тред атаки, родитель и спикер.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка атакующего.
     *
     * @return array{0: string, 1: string|null, 2: array<string, mixed>} Тред.
     */
    private function thread(GameBattleRecord $battle, array $row): array
    {
        $threadId = 'attack-wide-' . (is_int($row['id'] ?? null) ? $row['id'] : 0);

        return [
            $threadId,
            GameCombatThread::buildAttack($battle, $threadId)['parentId'],
            $this->chat->getSpeakerOf($row['attacker_kind'] ?? 'character', $row['attacker_id'] ?? 0),
        ];
    }

    /**
     * Прикрытие строки.
     *
     * @param array<string, mixed> $item Показанная строка.
     * @param array<int, array<string, mixed>> $covers Прикрытия.
     *
     * @return array<string, mixed>|null Прикрытие.
     */
    private function cover(array $item, array $covers): ?array
    {
        $rowId = is_int($item['row']['id'] ?? null) ? $item['row']['id'] : 0;

        return is_array($covers[$rowId] ?? null) ? $covers[$rowId] : null;
    }
}
