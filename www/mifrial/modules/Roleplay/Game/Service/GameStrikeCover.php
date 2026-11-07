<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Броски защиты исходной цели и прикрывающих.
 */
final class GameStrikeCover
{
    private readonly GameBattleMarks $marks;

    private readonly GameStrikeCoverInfo $info;

    private readonly GameStrikeCoverAdvantages $coverAdvantages;

    private readonly GameStrikeCoverRerate $rerate;

    /**
     * Создаёт расчёт прикрытия.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeResolution $resolution Расчёт бросков.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeResolution $resolution,
    ) {
        $this->marks = new GameBattleMarks();
        $this->info = new GameStrikeCoverInfo();
        $this->coverAdvantages = new GameStrikeCoverAdvantages();
        $this->rerate = new GameStrikeCoverRerate($this->rules, $this->resolution);
    }

    /**
     * Считает защиты исходной цели и прикрывающих, выбирает победителя.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array{
     *     winner: array<string, mixed>|null,
     *     failed: bool,
     *     candidates: array<string, array<string, mixed>>
     * } Итог.
     */
    public function resolve(GameStrikeCoverRun $run, array $row): array
    {
        $target = $this->rows->rowTarget($row);
        $primaryKey = $this->rows->targetKey($target);
        $attacker = $this->attackerOf($run->open);
        $candidates = [
            $primaryKey => $this->primary($run, $target, $attacker),
        ];
        $winner = null;
        foreach (GameStrikeTargetRow::jsonRows($row, 'coverers') as $entry) {
            $cover = $this->cover($run, $entry, $attacker);
            if ($cover === null) {
                continue;
            }

            [$key, $candidate] = $cover;
            $candidates[$key] = $candidate;
            if ($candidate['passed'] && ($winner === null || $candidate['rating'] > $winner['rating'])) {
                $winner = $candidate;
            }
        }

        return [
            'winner' => $winner,
            'failed' => $winner === null,
            'candidates' => $candidates,
        ];
    }

    /**
     * Собирает описание прикрытия для чата.
     *
     * @param array<string, mixed> $candidates Кандидаты защит.
     * @param string $primaryKey Ключ исходной цели.
     * @param string|null $winnerKey Ключ победителя.
     *
     * @return array<string, mixed>|null Описание или null.
     */
    public function info(array $candidates, string $primaryKey, ?string $winnerKey): ?array
    {
        return $this->info->info($candidates, $primaryKey, $winnerKey);
    }

    /**
     * Кандидат исходной цели.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array{type: string, id: int} $target Цель.
     * @param array{type: string, id: int}|null $attacker Атакующий.
     *
     * @return array<string, mixed> Кандидат.
     */
    private function primary(GameStrikeCoverRun $run, array $target, ?array $attacker): array
    {
        $marks = $this->defenseMarks($run->marks, $target, $attacker, $run->choice['reaction']);
        $candidate = $this->candidate(
            $run,
            $this->rows->targetOpen($run->open, $target),
            $run->choice,
            $run->targetPending,
            $marks['advantages'],
        );

        return [
            'actor' => $target,
            'choice' => $run->choice,
            'ruleCode' => '',
            'expectedSheetVersion' => $run->targetSheetVersion,
            'cost' => 0,
            'markKeys' => $marks['keys'],
            'reactionRuleCode' => $run->choice['reaction'] === 'ignore'
                ? null
                : $this->reactionRuleCode($run, $run->choice['reaction']),
            'attackerRoll' => $candidate['attackerRoll'],
            'defenderRoll' => $candidate['defenderRoll'],
            'passed' => $candidate['passed'],
            'rating' => $candidate['rating'],
            'spends' => [],
        ];
    }

    /**
     * Кандидат одного прикрывающего.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param mixed $entry Запись прикрытия.
     * @param array{type: string, id: int}|null $attacker Атакующий.
     *
     * @return array{0: string, 1: array<string, mixed>}|null Пара или null.
     */
    private function cover(GameStrikeCoverRun $run, mixed $entry, ?array $attacker): ?array
    {
        if (!is_array($entry)) {
            return null;
        }

        $actor = GameStrikeTargetRow::pair($entry['actor'] ?? null);
        $reaction = $entry['reaction'] ?? null;
        if ($actor === null || !is_string($reaction)) {
            return null;
        }

        return [
            $this->rows->targetKey($actor),
            $this->coverCandidate($run, $entry, $actor, $reaction, $attacker),
        ];
    }

    /**
     * Бросок и запись одного прикрывающего.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $entry Запись прикрытия.
     * @param array{type: string, id: int} $actor Прикрывающий.
     * @param string $reaction Реакция.
     * @param array{type: string, id: int}|null $attacker Атакующий.
     *
     * @return array<string, mixed> Запись кандидата.
     */
    private function coverCandidate(
        GameStrikeCoverRun $run,
        array $entry,
        array $actor,
        string $reaction,
        ?array $attacker,
    ): array {
        $choice = $this->coverChoice($entry, $reaction);
        $open = array_merge($run->open, [
            'defender_kind' => $actor['type'],
            'defender_id' => $actor['id'],
        ]);
        $advantages = $this->coverAdvantages->of(
            $run->slice,
            is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '',
        );
        $marks = $this->defenseMarks($run->marks, $actor, $attacker, $reaction);
        $candidate = $this->candidate(
            $run,
            $open,
            $choice,
            $run->pending,
            array_merge($advantages, $marks['advantages']),
        );

        return $this->coverRecord($run, $entry, $actor, $choice, $marks, $candidate);
    }

    /**
     * Запись кандидата прикрывающего.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $entry Запись прикрытия.
     * @param array{type: string, id: int} $actor Прикрывающий.
     * @param array<string, mixed> $choice Защита.
     * @param array{advantages: array<int, RollAdvantage>, keys: array<int, string>} $marks Метки.
     * @param array<string, mixed> $candidate Итог броска.
     *
     * @return array<string, mixed> Запись.
     */
    private function coverRecord(
        GameStrikeCoverRun $run,
        array $entry,
        array $actor,
        array $choice,
        array $marks,
        array $candidate,
    ): array {
        return [
            'actor' => $actor,
            'choice' => $choice,
            'ruleCode' => is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '',
            'expectedSheetVersion' => is_int($entry['expectedSheetVersion'] ?? null)
                ? $entry['expectedSheetVersion']
                : 0,
            'cost' => is_int($entry['cost'] ?? null) ? $entry['cost'] : 0,
            'markKeys' => $marks['keys'],
            'reactionRuleCode' => $this->reactionRuleCode($run, $choice['reaction']),
            'attackerRoll' => $candidate['attackerRoll'],
            'defenderRoll' => $candidate['defenderRoll'],
            'passed' => $candidate['passed'],
            'rating' => $candidate['rating'],
            'spends' => [],
        ];
    }

    /**
     * Защита прикрывающего из его записи.
     *
     * @param array<string, mixed> $entry Запись прикрытия.
     * @param string $reaction Реакция.
     *
     * @return array<string, mixed> Защита.
     */
    private function coverChoice(array $entry, string $reaction): array
    {
        return [
            'reaction' => $reaction,
            'blockItemInventoryId' => $entry['blockItemInventoryId'] ?? null,
            'blockItemProfileIndex' => $entry['blockItemProfileIndex'] ?? null,
            'blockItemRuleCode' => $entry['blockItemRuleCode'] ?? null,
            'turn' => false,
        ];
    }

    /**
     * Бросок одной защиты против уже брошенной атаки.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $open Строка удара с нужным защитником.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $pending Ожидание.
     * @param array<int, RollAdvantage> $advantages Дополнительные преимущества.
     *
     * @return array{attackerRoll: array<string, mixed>, defenderRoll: array<string, mixed>|null,
     *               passed: bool, rating: int} Итог.
     */
    private function candidate(
        GameStrikeCoverRun $run,
        array $open,
        array $choice,
        array $pending,
        array $advantages,
    ): array {
        if ($choice['reaction'] === 'ignore') {
            return [
                'attackerRoll' => $run->attackerRoll,
                'defenderRoll' => null,
                'passed' => false,
                'rating' => 0,
            ];
        }

        $reactionRuleCode = $this->reactionRuleCode($run, $choice['reaction']);
        $defenderRoll = $this->defenderRoll($run, $open, $choice, $pending, $reactionRuleCode, $advantages);
        $attackerRoll = $this->rerate->of($run, $open, $choice, $defenderRoll);

        return [
            'attackerRoll' => $attackerRoll,
            'defenderRoll' => $defenderRoll,
            'passed' => $defenderRoll !== null && ($defenderRoll['success'] ?? false) === true,
            'rating' => is_int($defenderRoll['rating'] ?? null) ? $defenderRoll['rating'] : 0,
        ];
    }

    /**
     * Бросок уклонения или блока.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $open Строка удара.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $pending Ожидание.
     * @param string|null $reactionRuleCode Код карточки реакции.
     * @param array<int, RollAdvantage> $advantages Преимущества.
     *
     * @return array<string, mixed>|null Бросок или null.
     */
    private function defenderRoll(
        GameStrikeCoverRun $run,
        array $open,
        array $choice,
        array $pending,
        ?string $reactionRuleCode,
        array $advantages,
    ): ?array {
        if ($choice['reaction'] === 'dodge') {
            return $this->dodgeRoll($run, $open, $pending, $reactionRuleCode, $advantages);
        }

        if ($choice['reaction'] === 'block') {
            return $this->blockRoll($run, $open, $choice, $pending, $reactionRuleCode, $advantages);
        }

        return null;
    }

    /**
     * Бросок уклонения.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $open Строка удара.
     * @param array<string, mixed> $pending Ожидание.
     * @param string|null $reactionRuleCode Код карточки реакции.
     * @param array<int, RollAdvantage> $advantages Преимущества.
     *
     * @return array<string, mixed> Бросок.
     */
    private function dodgeRoll(
        GameStrikeCoverRun $run,
        array $open,
        array $pending,
        ?string $reactionRuleCode,
        array $advantages,
    ): array {
        return $this->resolution->rolls->dodgeRollOf(
            $run->game,
            $open,
            $run->hitCode,
            $run->attackerRoll,
            $pending,
            $reactionRuleCode,
            $run->slice,
            $advantages,
            $run->shotDistance,
        );
    }

    /**
     * Бросок блока.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $open Строка удара.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $pending Ожидание.
     * @param string|null $reactionRuleCode Код карточки реакции.
     * @param array<int, RollAdvantage> $advantages Преимущества.
     *
     * @return array<string, mixed> Бросок.
     */
    private function blockRoll(
        GameStrikeCoverRun $run,
        array $open,
        array $choice,
        array $pending,
        ?string $reactionRuleCode,
        array $advantages,
    ): array {
        return $this->resolution->rolls->blockRollOf(
            $run->game,
            $open,
            $choice,
            $run->hitCode,
            $run->attackerRoll,
            $pending,
            $reactionRuleCode,
            $run->slice,
            $advantages,
        );
    }

    /**
     * Атакующий из строки удара.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return array{type: string, id: int}|null Пара или null.
     */
    private function attackerOf(array $open): ?array
    {
        $kind = $open['attacker_kind'] ?? null;
        $id = $open['attacker_id'] ?? null;
        if (!is_string($kind) || !is_int($id) || !in_array($kind, ['character', 'npc'], true) || $id < 1) {
            return null;
        }

        return ['type' => $kind, 'id' => $id];
    }

    /**
     * Метки защиты цели или прикрывающего.
     *
     * @param array<int, array<string, mixed>> $marks Метки боя.
     * @param array{type: string, id: int} $subject Защитник.
     * @param array{type: string, id: int}|null $attacker Атакующий.
     * @param mixed $reaction Реакция.
     *
     * @return array{advantages: array<int, RollAdvantage>, keys: array<int, string>} Метки.
     */
    private function defenseMarks(array $marks, array $subject, ?array $attacker, mixed $reaction): array
    {
        if ($reaction === 'ignore' || $attacker === null) {
            return ['advantages' => [], 'keys' => []];
        }

        return $this->marks->forDefense($marks, $subject, $attacker);
    }

    /**
     * Код карточки реакции.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param string $reaction Реакция.
     *
     * @return string|null Код.
     */
    private function reactionRuleCode(GameStrikeCoverRun $run, string $reaction): ?string
    {
        return $this->rules->cards->reactionRuleCode(
            $run->game->getSpaceId(),
            $run->game->getRulesRevision(),
            $reaction,
        );
    }

}
