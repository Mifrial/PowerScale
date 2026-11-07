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
            if ($this->states->isWound($row) || !is_string($row['stateRuleCode'] ?? null) || !is_int($row['id'] ?? null)) {
                continue;
            }

            foreach ($this->dot->tick($game, $slice, $sheet, $row) as $operation) {
                $operations[] = $operation;
            }
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
            $operations[] = ['kind' => 'refillTurnResource'];
        }

        return $operations;
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
