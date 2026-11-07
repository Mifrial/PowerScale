<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;
use Mifrial\Roleplay\Rule\Spec\AbilityCombat;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Броски защиты исходной цели и прикрывающих.
 */
final class GameStrikeCover
{
    private readonly GameBattleMarks $marks;

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
    }

    /**
     * Считает защиты исходной цели и прикрывающих, выбирает победителя.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed> $conditions Условия.
     * @param array<string, mixed> $choice Защита исходной цели.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $hitCode Карточка попадания.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $pending Ожидание.
     * @param array<string, mixed> $targetPending Ожидание исходной цели.
     * @param string $profileType Профиль.
     * @param bool $isPush Толчок.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела исходной цели.
     * @param int $targetSheetVersion Версия листа исходной цели.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     *
     * @return array{
     *     winner: array<string, mixed>|null,
     *     failed: bool,
     *     candidates: array<string, array<string, mixed>>
     * } Итог.
     */
    public function resolve(
        GameRecord $game,
        array $open,
        array $row,
        array $conditions,
        array $choice,
        array $attackerRoll,
        string $hitCode,
        CharacterRuleSlice $slice,
        array $pending,
        array $targetPending,
        string $profileType,
        bool $isPush,
        ?DimensionalNumber $shotDistance = null,
        int $targetSheetVersion = 0,
        array $marks = [],
    ): array {
        $target = $this->rows->rowTarget($row);
        $targetOpen = $this->rows->targetOpen($open, $target);
        $attacker = $this->attackerOf($open);
        $targetMarks = $choice['reaction'] === 'ignore' || $attacker === null
            ? ['advantages' => [], 'keys' => []]
            : $this->marks->forDefense($marks, $target, $attacker);
        $candidates = [];
        $primaryKey = $this->rows->targetKey($target);
        $primary = $this->candidate(
            $game,
            $targetOpen,
            $conditions,
            $choice,
            $attackerRoll,
            $hitCode,
            $slice,
            $targetPending,
            $profileType,
            $isPush,
            $targetMarks['advantages'],
            $shotDistance,
        );
        $candidates[$primaryKey] = [
            'actor' => $target,
            'choice' => $choice,
            'ruleCode' => '',
            'expectedSheetVersion' => $targetSheetVersion,
            'cost' => 0,
            'markKeys' => $targetMarks['keys'],
            'reactionRuleCode' => $choice['reaction'] === 'ignore'
                ? null
                : $this->rules->cards->reactionRuleCode(
                    $game->getSpaceId(),
                    $game->getRulesRevision(),
                    $choice['reaction'],
                ),
            'attackerRoll' => $primary['attackerRoll'],
            'defenderRoll' => $primary['defenderRoll'],
            'passed' => $primary['passed'],
            'rating' => $primary['rating'],
            'spends' => [],
        ];

        $winner = null;
        foreach ($this->jsonRows($row, 'coverers') as $entry) {
            $actor = $entry['actor'] ?? null;
            if (
                !is_array($actor)
                || !is_string($actor['type'] ?? null)
                || !is_int($actor['id'] ?? null)
                || !is_string($entry['reaction'] ?? null)
            ) {
                continue;
            }

            $coverChoice = [
                'reaction' => $entry['reaction'],
                'blockItemInventoryId' => $entry['blockItemInventoryId'] ?? null,
                'blockItemProfileIndex' => $entry['blockItemProfileIndex'] ?? null,
                'blockItemRuleCode' => $entry['blockItemRuleCode'] ?? null,
                'turn' => false,
            ];
            $coverOpen = array_merge($open, [
                'defender_kind' => $actor['type'],
                'defender_id' => $actor['id'],
            ]);
            $advantages = $this->advantages(
                $slice,
                is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '',
            );
            $coverMarks = $coverChoice['reaction'] === 'ignore' || $attacker === null
                ? ['advantages' => [], 'keys' => []]
                : $this->marks->forDefense($marks, $actor, $attacker);
            $candidate = $this->candidate(
                $game,
                $coverOpen,
                $conditions,
                $coverChoice,
                $attackerRoll,
                $hitCode,
                $slice,
                $pending,
                $profileType,
                $isPush,
                array_merge($advantages, $coverMarks['advantages']),
            );
            $key = $this->rows->targetKey($actor);
            $cost = is_int($entry['cost'] ?? null) ? $entry['cost'] : 0;
            $reactionRuleCode = $this->rules->cards->reactionRuleCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $coverChoice['reaction'],
            );
            $candidates[$key] = [
                'actor' => $actor,
                'choice' => $coverChoice,
                'ruleCode' => is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '',
                'expectedSheetVersion' => is_int($entry['expectedSheetVersion'] ?? null)
                    ? $entry['expectedSheetVersion']
                    : 0,
                'cost' => $cost,
                'markKeys' => $coverMarks['keys'],
                'reactionRuleCode' => $reactionRuleCode,
                'attackerRoll' => $candidate['attackerRoll'],
                'defenderRoll' => $candidate['defenderRoll'],
                'passed' => $candidate['passed'],
                'rating' => $candidate['rating'],
                'spends' => [],
            ];
            if ($candidate['passed'] && ($winner === null || $candidate['rating'] > $winner['rating'])) {
                $winner = $candidates[$key];
            }
        }

        return [
            'winner' => $winner,
            'failed' => $winner === null,
            'candidates' => $candidates,
        ];
    }

    /**
     * Бросок одной защиты против уже брошенной атаки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара с нужным защитником.
     * @param array<string, mixed> $conditions Условия строки.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $hitCode Карточка попадания.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $pending Ожидание.
     * @param string $profileType Профиль.
     * @param bool $isPush Толчок.
     * @param array<int, RollAdvantage> $extraAdvantages Дополнительные преимущества.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     *
     * @return array{attackerRoll: array<string, mixed>, defenderRoll: array<string, mixed>|null,
     *               passed: bool, rating: int} Итог.
     */
    private function candidate(
        GameRecord $game,
        array $open,
        array $conditions,
        array $choice,
        array $attackerRoll,
        string $hitCode,
        CharacterRuleSlice $slice,
        array $pending,
        string $profileType,
        bool $isPush,
        array $extraAdvantages,
        ?DimensionalNumber $shotDistance = null,
    ): array {
        if ($choice['reaction'] === 'ignore') {
            return [
                'attackerRoll' => $attackerRoll,
                'defenderRoll' => null,
                'passed' => false,
                'rating' => 0,
            ];
        }

        $reactionRuleCode = $this->rules->cards->reactionRuleCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['reaction'],
        );
        $defenderRoll = null;
        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->resolution->rolls->dodgeRollOf(
                $game,
                $open,
                $hitCode,
                $attackerRoll,
                $pending,
                $reactionRuleCode,
                $slice,
                $extraAdvantages,
                $shotDistance,
            );
            $attackerRoll = $profileType === 'shoot' || $profileType === 'throw'
                ? $this->resolution->defense->rerateRangedHit($game, $open, $conditions, $attackerRoll, $defenderRoll, 'dodge')
                : $this->rules->hitCheck->rerate($attackerRoll, $defenderRoll);
        } elseif ($choice['reaction'] === 'block') {
            $defenderRoll = $this->resolution->rolls->blockRollOf(
                $game,
                $open,
                $choice,
                $hitCode,
                $attackerRoll,
                $pending,
                $reactionRuleCode,
                $slice,
                $extraAdvantages,
            );
            if ($isPush) {
                $attackerRoll = $this->rules->hitCheck->rerate($attackerRoll, $defenderRoll);
            } elseif ($profileType === 'shoot' || $profileType === 'throw') {
                $attackerRoll = $this->resolution->defense->rerateRangedHit(
                    $game,
                    $open,
                    $conditions,
                    $attackerRoll,
                    $defenderRoll,
                    'block',
                );
            }
        }

        return [
            'attackerRoll' => $attackerRoll,
            'defenderRoll' => $defenderRoll,
            'passed' => $defenderRoll !== null && ($defenderRoll['success'] ?? false) === true,
            'rating' => is_int($defenderRoll['rating'] ?? null) ? $defenderRoll['rating'] : 0,
        ];
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
     * Помехи защите прикрывающего из его карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Карточка прикрытия.
     *
     * @return array<int, RollAdvantage> Помехи.
     */
    private function advantages(CharacterRuleSlice $slice, string $ruleCode): array
    {
        $rule = $slice->findLive($ruleCode);
        if ($rule === null || $rule->isSpecBroken()) {
            return [];
        }

        $cover = AbilityCombat::cover($rule->getSpecDocument());
        if (!$cover instanceof CoverAlly) {
            return [];
        }

        $rows = [];
        foreach ($cover->getDefenseAdvantages() as $entry) {
            $rows[] = new RollAdvantage(
                $entry->getSourceCode(),
                $entry->getDelta(),
                AdvantageSourceLabels::getLabel($entry->getSourceCode()),
            );
        }

        return $rows;
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
        $primary = $candidates[$primaryKey] ?? null;
        if (!is_array($primary)) {
            return null;
        }

        $primaryActor = $primary['actor'] ?? null;
        $primaryChoice = $primary['choice'] ?? null;
        if (
            !is_array($primaryActor)
            || !is_string($primaryActor['type'] ?? null)
            || !is_int($primaryActor['id'] ?? null)
            || !is_array($primaryChoice)
        ) {
            return null;
        }

        $attempts = [];
        foreach ($candidates as $key => $candidate) {
            if ($key === $primaryKey || !is_array($candidate)) {
                continue;
            }

            $actor = $candidate['actor'] ?? null;
            $choice = $candidate['choice'] ?? null;
            if (
                !is_array($actor)
                || !is_string($actor['type'] ?? null)
                || !is_int($actor['id'] ?? null)
                || !is_array($choice)
            ) {
                continue;
            }

            $attempts[] = [
                'key' => $key,
                'actor' => ['type' => $actor['type'], 'id' => $actor['id']],
                'ruleCode' => is_string($candidate['ruleCode'] ?? null) ? $candidate['ruleCode'] : '',
                'reaction' => is_string($choice['reaction'] ?? null) ? $choice['reaction'] : '',
                'blockItemRuleCode' => is_string($choice['blockItemRuleCode'] ?? null)
                    ? $choice['blockItemRuleCode']
                    : '',
                'cost' => is_int($candidate['cost'] ?? null) ? $candidate['cost'] : 0,
                'roll' => is_array($candidate['defenderRoll'] ?? null) ? $candidate['defenderRoll'] : null,
                'passed' => ($candidate['passed'] ?? false) === true,
                'winner' => $winnerKey !== null && $key === $winnerKey,
            ];
        }

        return [
            'target' => ['type' => $primaryActor['type'], 'id' => $primaryActor['id']],
            'targetReaction' => is_string($primaryChoice['reaction'] ?? null) ? $primaryChoice['reaction'] : 'ignore',
            'targetReactionRuleCode' => is_string($primary['reactionRuleCode'] ?? null)
                ? $primary['reactionRuleCode']
                : null,
            'targetRoll' => is_array($primary['defenderRoll'] ?? null) ? $primary['defenderRoll'] : null,
            'targetActual' => $winnerKey === $primaryKey,
            'attempts' => $attempts,
        ];
    }

    /**
     * Список JSON-записей строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return list<array<string, mixed>> Записи.
     */
    private function jsonRows(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }
}
