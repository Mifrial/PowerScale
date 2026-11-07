<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeTargetOptions;
use Mifrial\Roleplay\Game\Dto\GameStrikeWriteScope;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Расчёт одной цели широкого удара.
 */
final class GameStrikeTargetFlow
{
    /**
     * Ключи меток, чьи защиты реально применены.
     *
     * @var array<int, string>
     */
    private array $markKeys = [];

    private readonly GameBattleMarks $marks;

    /**
     * Создаёт расчёт цели.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeTargetReaction $reaction Реакция цели.
     * @param GameStrikeTargetWrite $write Запись листа.
     * @param GameStrikeTargetRolls $rolls Броски и урон цели.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameStrikeTargetReaction $reaction,
        private readonly GameStrikeTargetWrite $write,
        private readonly GameStrikeTargetRolls $rolls,
    ) {
        $this->marks = new GameBattleMarks();
    }

    /**
     * Сбрасывает применённые метки.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->markKeys = [];
    }

    /**
     * Ключи меток, чьи защиты пошли в итог.
     *
     * @return array<int, string> Ключи.
     */
    public function markKeys(): array
    {
        return $this->markKeys;
    }

    /**
     * Одна цель широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка цели.
     * @param array<string, mixed> $choice Защита.
     * @param string|null $refusalCode Non-CAS отказ.
     * @param DimensionalNumber $damage Урон.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $hitCode Код проверки.
     * @param callable(): DimensionalNumber $penetrationFactory Ленивое проникновение.
     * @param GameStrikeWriteScope $scope Права записи.
     * @param GameStrikeTargetOptions $options Дополнительные данные.
     *
     * @return array<string, mixed> Запись цели.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    public function one(
        GameRecord $game, array $open, array $row, array $choice, ?string $refusalCode,
        DimensionalNumber $damage, array $attackerRoll, string $hitCode, callable $penetrationFactory,
        GameStrikeWriteScope $scope, GameStrikeTargetOptions $options,
    ): array {
        $target = GameStrikeTargetRow::defender($row);
        if ($refusalCode !== null) {
            return $this->refusalRow($target, $refusalCode);
        }

        $reaction = $this->reactionFor($game, $row, $choice, $hitCode, $options->prepared, $options->resolved);
        if ($reaction['insufficient']) {
            return $this->emptyRow($target);
        }

        return $this->resolve(
            $game, $open, $row, $target, $choice, $reaction, $attackerRoll, $hitCode,
            $penetrationFactory, $damage, $scope, $options,
        );
    }

    /**
     * Реакция цели по готовому, подготовленному или новому расчёту.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $row Строка цели.
     * @param array<string, mixed> $choice Защита.
     * @param string $hitCode Код проверки.
     * @param array<string, mixed>|null $prepared Подготовка строки.
     * @param array<string, mixed>|null $resolved Готовая защита.
     *
     * @return array<string, mixed> Реакция.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если карточка реакции битая.
     */
    private function reactionFor(
        GameRecord $game, array $row, array $choice, string $hitCode, ?array $prepared, ?array $resolved,
    ): array {
        if ($resolved !== null) {
            return [
                'spends' => [],
                'pending' => $this->reaction->pendingAfter(),
                'slice' => $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
                'reactionRuleCode' => $resolved['reactionRuleCode'],
                'insufficient' => false,
                'token' => null,
            ];
        }

        if ($prepared !== null) {
            return $prepared['reaction'];
        }

        return $this->reaction->reactionOf(
            $game,
            GameStrikeTargetRow::defender($row),
            $choice,
            GameStrikeTargetRow::conditions($row),
            $hitCode,
        );
    }

    /**
     * Полный расчёт цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка цели.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $reaction Реакция.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $hitCode Код проверки.
     * @param callable(): DimensionalNumber $penetrationFactory Ленивое проникновение.
     * @param DimensionalNumber $damage Урон.
     * @param GameStrikeWriteScope $scope Права записи.
     * @param GameStrikeTargetOptions $options Дополнительные данные.
     *
     * @return array<string, mixed> Запись цели.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    private function resolve(
        GameRecord $game, array $open, array $row, array $target, array $choice, array $reaction,
        array $attackerRoll, string $hitCode, callable $penetrationFactory, DimensionalNumber $damage,
        GameStrikeWriteScope $scope, GameStrikeTargetOptions $options,
    ): array {
        $resolved = $options->resolved;
        if ($resolved !== null) {
            $choice = $resolved['choice'];
            $attackerRoll = $resolved['attackerRoll'];
        }

        $defense = $this->defensePart($game, $open, $row, $target, $choice, $attackerRoll, $reaction, $hitCode, $options);
        $success = $this->successOfTarget($game, $open, $target, $defense, $reaction);
        $outcome = $this->rolls->damageOf(
            $game, $open, $target, $choice, $defense['defenderRoll'], $success, $damage,
            $penetrationFactory, [], $attackerRoll,
        );
        $record = $this->recordOf($target, $success, $outcome, $defense['defenderRoll']);
        $this->persist($game, $open, $target, $choice, $success, $outcome, $reaction, $defense, $record, $scope);

        return $record;
    }

    /**
     * Защита цели и применённые метки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка цели.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $reaction Реакция.
     * @param string $hitCode Код проверки.
     * @param GameStrikeTargetOptions $options Дополнительные данные.
     *
     * @return array{defenderRoll: array<string, mixed>|null, autoFail: bool, success: int} Защита.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    private function defensePart(
        GameRecord $game, array $open, array $row, array $target, array $choice,
        array $attackerRoll, array $reaction, string $hitCode, GameStrikeTargetOptions $options,
    ): array {
        $resolved = $options->resolved;
        $markDefense = $this->markDefense($open, $target, $choice, $options->marks, $resolved);
        $defense = $resolved !== null
            ? $this->rolls->resolvedDefense(
                $attackerRoll,
                $resolved['defenderRoll'],
                is_string($choice['reaction'] ?? null) ? $choice['reaction'] : 'ignore',
            )
            : $this->rolls->defenseOf(
                $game, $open, $target, $choice, $attackerRoll, $reaction['pending'],
                $reaction['reactionRuleCode'], $reaction['slice'], $hitCode,
                GameStrikeTargetRow::conditions($row), $reaction['token']['advantage'] ?? null,
                array_merge($options->extraAdvantages, $markDefense['advantages']),
            );
        $applied = $resolved !== null ? $this->stringList($resolved['markKeys'] ?? null) : $markDefense['keys'];
        if ($applied !== []) {
            $this->markKeys = array_merge($this->markKeys, $applied);
        }

        return $defense;
    }

    /**
     * Преимущества и ключи меток защиты.
     *
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     * @param array<string, mixed>|null $resolved Готовая защита.
     *
     * @return array{advantages: array<int, RollAdvantage>, keys: array<int, string>} Метки.
     */
    private function markDefense(
        array $open, array $target, array $choice, array $marks, ?array $resolved,
    ): array {
        if ($resolved !== null || !in_array($choice['reaction'] ?? null, ['dodge', 'block'], true)) {
            return ['advantages' => [], 'keys' => []];
        }

        $attacker = $this->attackerOf($open);

        return $attacker === null
            ? ['advantages' => [], 'keys' => []]
            : $this->marks->forDefense($marks, $target, $attacker);
    }

    /**
     * Успех цели с хвостом прошлого действия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array{defenderRoll: array<string, mixed>|null, autoFail: bool, success: int} $defense Защита.
     * @param array<string, mixed> $reaction Реакция.
     *
     * @return int Успех.
     */
    private function successOfTarget(
        GameRecord $game, array $open, array $target, array $defense, array $reaction,
    ): int {
        $slice = $reaction['slice'] ?? $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $lastStrike = new GameLastStrikeSnapshot();
        $previousSr = $lastStrike->previousSr(
            $reaction['pending'],
            is_string($open['attacker_kind'] ?? null) ? $open['attacker_kind'] : '',
            is_int($open['attacker_id'] ?? null) ? $open['attacker_id'] : 0,
            $target['type'] . ':' . $target['id'],
        );

        return $lastStrike->boostSuccess(
            $slice,
            is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
            $defense['success'],
            $previousSr,
        );
    }

    /**
     * Запись цели по итогу урона.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param int $success Успех.
     * @param array<string, mixed> $outcome Итог урона.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     *
     * @return array<string, mixed> Запись.
     */
    private function recordOf(array $target, int $success, array $outcome, ?array $defenderRoll): array
    {
        $record = GameStrikeTargetRow::row(
            $target,
            null,
            $success,
            $this->amounts->view($outcome['effectiveDamage']),
            $this->amounts->view($outcome['resistance']),
            $outcome['soak'] === null ? null : $this->amounts->view($outcome['soak']),
            $this->amounts->view($outcome['injury']),
            $defenderRoll,
        );
        $record['appliedSr'] = $outcome['appliedSr'];
        $record['srCap'] = $outcome['srCap'];
        if ($outcome['durabilityShave'] > 0) {
            $record['durabilityShave'] = $outcome['durabilityShave'];
        }

        return $record;
    }

    /**
     * Пишет лист цели и закрывает траты реакции.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param int $success Успех.
     * @param array<string, mixed> $outcome Итог урона.
     * @param array<string, mixed> $reaction Реакция.
     * @param array{defenderRoll: array<string, mixed>|null, autoFail: bool, success: int} $defense Защита.
     * @param array<string, mixed> $record Запись.
     * @param GameStrikeWriteScope $scope Права записи.
     *
     * @return void
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если лист или строка битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function persist(
        GameRecord $game, array $open, array $target, array $choice, int $success,
        array $outcome, array $reaction, array $defense, array &$record, GameStrikeWriteScope $scope,
    ): void {
        $spends = $reaction['spends'];
        if (!$defense['autoFail'] || $spends !== []) {
            $record['sheetVersion'] = $defense['autoFail']
                ? $this->write->writeResources($game, $target, $choice['expectedSheetVersion'], $spends, $scope)
                : $this->write->writeTarget(
                    $game, $open, $target, $choice, $outcome['injury'], $success, $spends, $scope, $record,
                );
        }

        if ($reaction['reactionRuleCode'] !== null) {
            $this->reaction->complete(
                $game,
                $target,
                $reaction['pending'],
                $spends,
                $reaction['reactionRuleCode'],
                $reaction['slice'],
            );
        }

        $record['chat'] = true;
    }

    /**
     * Строка отказа без расчёта.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param string $refusalCode Код отказа.
     *
     * @return array<string, mixed> Запись.
     */
    private function refusalRow(array $target, string $refusalCode): array
    {
        return GameStrikeTargetRow::row($target, $refusalCode, null, null, null);
    }

    /**
     * Нулевая строка при нехватке ресурсов.
     *
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Запись.
     */
    private function emptyRow(array $target): array
    {
        $zero = $this->amounts->view($this->amounts->zero());

        return GameStrikeTargetRow::row($target, null, null, $zero, $zero, null, $zero);
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
     * Список строк из недоверенного значения.
     *
     * @param mixed $raw Значение.
     *
     * @return array<int, string> Строки.
     */
    private function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (is_string($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
