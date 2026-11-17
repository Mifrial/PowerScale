<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Считает операции конца хода одного листа. Лист не пишет.
 */
final class GameTurnTicks
{
    /**
     * Создаёт расчёт.
     *
     * @param GameTurnTickWound $wound Раны и свёртывание.
     * @param GameTurnTickBlood $blood Кровь и истощение.
     * @param GameTurnTickDot $dot Периодический урон.
     * @param GameTurnSheetStates $states Строки листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameTurnTickWound $wound,
        private readonly GameTurnTickBlood $blood,
        private readonly GameTurnTickDot $dot,
        private readonly GameTurnSheetStates $states,
    ) {
    }

    /**
     * Clotting, кровь, DOT и refill.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array<string, mixed>>} Итог.
     *
     * @throws GameInvalidException Если состояние или пул не позволяют ход.
     */
    public function build(GameRecord $game, CharacterRuleSlice $slice, array $sheet): array
    {
        $wounded = $this->woundPhase($game, $slice, $sheet);
        $blood = $this->blood->tick($game, $slice, $sheet, $wounded['bleed']);

        return [
            'operations' => [
                ...$wounded['operations'],
                ...$blood['operations'],
                ...$this->dotPhase($game, $slice, $sheet),
                ...$this->deviationPhase($slice, $sheet),
                ...$this->resourcePhase($slice, $sheet),
            ],
            'rolls' => [...$wounded['rolls'], ...$blood['rolls']],
        ];
    }

    /**
     * Ровно одна карточка или ноль. Две и больше — отказ хода.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return int Сколько карточек.
     */
    public function turnRefillCount(CharacterRuleSlice $slice): int
    {
        $count = 0;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Ресурс восстанавливается в начале своего хода.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код ресурса.
     *
     * @return bool true, если пул хода.
     */
    private function isTurnRefill(CharacterRuleSlice $slice, string $code): bool
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof ResourceSpec && $spec->isTurnRefill();
    }

    /**
     * Раны и их вклад в кровотечение.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array<string, mixed>>, bleed: int} Итог.
     *
     * @throws GameInvalidException Если маркер свёртывания битый.
     */
    private function woundPhase(GameRecord $game, CharacterRuleSlice $slice, array $sheet): array
    {
        $operations = [];
        $rolls = [];
        $bleed = 0;
        foreach ($this->states->rows($sheet) as $row) {
            if (!$this->states->isWound($row)) {
                continue;
            }

            $built = $this->wound->tick($game, $slice, $sheet, $row);
            $bleed += $built['tick'];
            foreach ($built['operations'] as $operation) {
                $operations[] = $operation;
            }

            if ($built['roll'] !== null) {
                $rolls[] = ['roll' => $built['roll'], 'text' => 'Свёртывание'];
            }
        }

        return ['operations' => $operations, 'rolls' => $rolls, 'bleed' => $bleed];
    }

    /**
     * Периодический урон незаживших состояний.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если сила состояния битая.
     */
    private function dotPhase(GameRecord $game, CharacterRuleSlice $slice, array $sheet): array
    {
        $operations = [];
        foreach ($this->states->rows($sheet) as $row) {
            if ($this->states->isWound($row) || !is_string($row['stateRuleCode'] ?? null)) {
                continue;
            }

            $runtime = ($row['runtime'] ?? false) === true;
            if (!$runtime && !is_int($row['id'] ?? null)) {
                continue;
            }

            foreach ($this->dot->tick($game, $slice, $sheet, $row) as $operation) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    /**
     * Затухание магического отклонения: сила −1, при нуле строка снимается.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<array<string, mixed>> Операции.
     */
    private function deviationPhase(CharacterRuleSlice $slice, array $sheet): array
    {
        $operations = [];
        foreach ($this->states->rows($sheet) as $row) {
            $code = $row['stateRuleCode'] ?? null;
            if (!is_string($code)) {
                continue;
            }

            $spec = $slice->findLive($code)?->getSpec();
            if (!$spec instanceof StateSpec || !$spec->isMagicDeviation()) {
                continue;
            }

            $id = $row['id'] ?? null;
            $value = $row['value'] ?? null;
            if (!is_int($id) || $id < 1 || !is_int($value)) {
                continue;
            }

            $operations[] = $value - 1 <= 0
                ? ['kind' => 'removeState', 'id' => $id]
                : ['kind' => 'patchState', 'id' => $id, 'value' => $value - 1];
        }

        return $operations;
    }

    /**
     * Остаток урона и пул хода.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если пулов больше одного.
     */
    private function resourcePhase(CharacterRuleSlice $slice, array $sheet): array
    {
        $operations = $this->remainderOperations($slice, $sheet);
        $pools = $this->turnRefillCount($slice);
        if ($pools > 1) {
            throw new GameInvalidException('Game turn resource is invalid');
        }

        if ($pools === 1) {
            $operation = ['kind' => 'refillTurnResource'];
            $limits = $this->refillLimits($slice, $sheet);
            if ($limits !== []) {
                $operation['limits'] = $limits;
            }

            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * Потолки пулов хода с учётом наложенных состояний.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист с состояниями.
     *
     * @return array<string, int|array{base: int, size: int}> Потолки по коду ресурса.
     */
    private function refillLimits(CharacterRuleSlice $slice, array $sheet): array
    {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $limits = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = $row['ruleCode'] ?? null;
            if (!is_string($code) || !$this->isTurnRefill($slice, $code)) {
                continue;
            }

            $limit = $this->rowLimit($row);
            if ($limit !== null) {
                $limits[$code] = $limit;
            }
        }

        return $limits;
    }

    /**
     * Потолок одной строки ресурса: база и сумма вкладов.
     *
     * @param array<string, mixed> $row Строка ресурса.
     *
     * @return int|array{base: int, size: int}|null Потолок или null.
     */
    private function rowLimit(array $row): int|array|null
    {
        $delta = 0;
        $bonuses = $row['bonuses'] ?? null;
        if (is_array($bonuses)) {
            foreach ($bonuses as $bonus) {
                if (is_array($bonus) && is_int($bonus['delta'] ?? null)) {
                    $delta += $bonus['delta'];
                }
            }
        }

        $base = $row['base'] ?? null;
        if (is_int($base)) {
            return max(0, $base + $delta);
        }

        if (is_array($base) && is_int($base['base'] ?? null) && is_int($base['size'] ?? null)) {
            return ['base' => max(0, $base['base'] + $delta), 'size' => $base['size']];
        }

        return null;
    }

    /**
     * Снятие остатков урона.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<array<string, mixed>> Операции.
     */
    private function remainderOperations(CharacterRuleSlice $slice, array $sheet): array
    {
        $operations = [];
        foreach ($this->states->rows($sheet) as $row) {
            $code = $row['stateRuleCode'] ?? null;
            $id = $row['id'] ?? null;
            if (!is_string($code) || !is_int($id)) {
                continue;
            }

            $spec = $slice->findLive($code)?->getSpec();
            if ($spec instanceof StateSpec && $spec->isDamageRemainder()) {
                $operations[] = ['kind' => 'removeState', 'id' => $id];
            }
        }

        return $operations;
    }
}
