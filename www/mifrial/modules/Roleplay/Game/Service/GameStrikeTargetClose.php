<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Расчёт и запись одной цели удара.
 */
final class GameStrikeTargetClose
{
    /**
     * Карта ожидания после последнего расчёта.
     *
     * @var array<string, mixed>
     */
    private array $pendingAfter = [];

    /**
     * Начинает расчёт с карты боя.
     *
     * @param array<string, mixed> $pending Карта.
     *
     * @return void
     */
    public function begin(array $pending): void
    {
        $this->pendingAfter = $pending;
    }

    /**
     * Карта ожидания после расчёта.
     *
     * @return array<string, mixed> Карта.
     */
    public function pendingAfter(): array
    {
        return $this->pendingAfter;
    }

    /**
     * Создаёт расчёт цели.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Расчёт бросков и урона.
     * @param GameStrikeSpend $spend Траты реакции.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeTargetChoices $choices Choices целей.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameStrikeTargetChoices $choices,
    ) {
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
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Запись цели.
     */
    public function one(
        GameRecord $game,
        array $open,
        array $row,
        array $choice,
        ?string $refusalCode,
        DimensionalNumber $damage,
        array $attackerRoll,
        string $hitCode,
        callable $penetrationFactory,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $target = GameStrikeTargetRow::defender($row);
        if ($refusalCode !== null) {
            return GameStrikeTargetRow::row($target, $refusalCode, null, null, null);
        }

        $reaction = $this->reactionOf($game, $target, $choice);
        if ($reaction['insufficient']) {
            $zero = $this->amounts->view($this->amounts->zero());

            return GameStrikeTargetRow::row($target, null, null, $zero, $zero, null, $zero);
        }

        $spends = $reaction['spends'];
        $slice = $reaction['slice'];
        $reactionRuleCode = $reaction['reactionRuleCode'];
        $lastStrike = new GameLastStrikeSnapshot();
        $previousSr = $lastStrike->previousSr(
            $reaction['pending'],
            is_string($open['attacker_kind'] ?? null) ? $open['attacker_kind'] : '',
            is_int($open['attacker_id'] ?? null) ? $open['attacker_id'] : 0,
            $target['type'] . ':' . $target['id'],
        );
        $defense = $this->defenseOf(
            $game,
            $open,
            $target,
            $choice,
            $attackerRoll,
            $reaction['pending'],
            $reactionRuleCode,
            $slice,
            $hitCode,
        );
        $slice ??= $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $success = $lastStrike->boostSuccess(
            $slice,
            is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
            $defense['success'],
            $previousSr,
        );
        $outcome = $this->damageOf(
            $game,
            $open,
            $target,
            $choice,
            $defense['defenderRoll'],
            $success,
            $damage,
            $penetrationFactory,
        );
        $record = GameStrikeTargetRow::row(
            $target,
            null,
            $success,
            $this->amounts->view($outcome['effectiveDamage']),
            $this->amounts->view($outcome['resistance']),
            $outcome['soak'] === null ? null : $this->amounts->view($outcome['soak']),
            $this->amounts->view($outcome['injury']),
            $defense['defenderRoll'],
        );
        if (!$defense['autoFail'] || $spends !== []) {
            $record['sheetVersion'] = $defense['autoFail']
                ? $this->sheetWrites->writeResources(
                    $game,
                    $target['type'],
                    $target['id'],
                    $choice['expectedSheetVersion'],
                    $spends,
                    $target,
                    fn (
                        CharacterConflictException|GameEconomyConflictException $exception,
                    ): array => $this->sheets->projectWriteConflict(
                        $game,
                        $target,
                        $exception,
                        $actorUserId,
                        $viewAll,
                    ),
                )
                : $this->writeTarget(
                    $game,
                    $open,
                    $target,
                    $choice,
                    $outcome['injury'],
                    $success,
                    $spends,
                    $actorUserId,
                    $viewAll,
                    $record,
                );
        }

        if ($reactionRuleCode !== null && $slice !== null) {
            $this->pendingAfter = (new GamePendingActionCosts())->complete(
                $slice,
                $this->choices->targetChoices($game, $target),
                $reactionRuleCode,
                $target['type'],
                $target['id'],
                $reaction['pending'],
                $this->spend->getSpendAmounts($spends),
            );
        }

        $record['chat'] = true;

        return $record;
    }

    /**
     * Траты и карта ожидания реакции цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>,
     *               slice: CharacterRuleSlice|null, reactionRuleCode: string|null, insufficient: bool}
     */
    private function reactionOf(GameRecord $game, array $target, array $choice): array
    {
        $pending = $this->pendingAfter;
        $spends = $this->spend->getReactionSpends(
            $game,
            $choice,
            $this->choices->targetChoices($game, $target),
            $this->sheets->getTargetSheet($game, $target),
        );
        $slice = null;
        $reactionRuleCode = null;
        if ($choice['reaction'] !== 'ignore') {
            $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
            $reactionRuleCode = $this->rules->reactionRuleCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $choice['reaction'],
            );
            if ($reactionRuleCode !== null) {
                $declared = (new GamePendingActionCosts())->declare(
                    $slice,
                    $this->choices->targetChoices($game, $target),
                    $reactionRuleCode,
                    $target['type'],
                    $target['id'],
                    $pending,
                    $this->spend->getSpendAmounts($spends),
                    false,
                );
                $spends = $this->spend->getSpendRows($declared['amounts']);
                $pending = $declared['pending'];
            }
        }

        $insufficient = $spends !== [] && !$this->rules->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->sheets->getTargetSheet($game, $target),
            $spends,
        );

        return [
            'spends' => $spends,
            'pending' => $pending,
            'slice' => $slice,
            'reactionRuleCode' => $reactionRuleCode,
            'insufficient' => $insufficient,
        ];
    }

    /**
     * Броски защиты и число успеха цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string|null $reactionRuleCode Карточка реакции.
     * @param CharacterRuleSlice|null $slice Срез.
     * @param string $hitCode Код проверки.
     *
     * @return array{defenderRoll: array<string, mixed>|null, autoFail: bool, success: int}
     */
    private function defenseOf(
        GameRecord $game,
        array $open,
        array $target,
        array $choice,
        array $attackerRoll,
        array $pending,
        ?string $reactionRuleCode,
        ?CharacterRuleSlice $slice,
        string $hitCode,
    ): array {
        $targetOpen = GameStrikeTargetRow::targetOpen($open, $target);
        $defenderRoll = null;
        $ratedRoll = $attackerRoll;
        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->resolution->dodgeRollOf(
                $game,
                $targetOpen,
                $hitCode,
                $attackerRoll,
                $pending,
                $reactionRuleCode,
                $slice ?? $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            );
            $ratedRoll = $this->rules->rerateHit($attackerRoll, $defenderRoll);
        }

        if ($choice['reaction'] === 'block') {
            $defenderRoll = $this->resolution->blockRollOf(
                $game,
                $targetOpen,
                $choice,
                $hitCode,
                $attackerRoll,
                $pending,
                $reactionRuleCode,
                $slice ?? $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            );
        }

        return [
            'defenderRoll' => $defenderRoll,
            'autoFail' => $this->rules->isHitAutoFail($attackerRoll['roll']),
            'success' => $this->resolution->successOf($ratedRoll, $defenderRoll, $choice['reaction']),
        ];
    }

    /**
     * Урон, сопротивление и повреждение цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     * @param int $success Успех.
     * @param DimensionalNumber $damage Урон.
     * @param callable(): DimensionalNumber $penetrationFactory Ленивое проникновение.
     *
     * @return array{effectiveDamage: DimensionalNumber, resistance: DimensionalNumber,
     *               soak: DimensionalNumber|null, injury: DimensionalNumber}
     */
    private function damageOf(
        GameRecord $game,
        array $open,
        array $target,
        array $choice,
        ?array $defenderRoll,
        int $success,
        DimensionalNumber $damage,
        callable $penetrationFactory,
    ): array {
        $targetOpen = GameStrikeTargetRow::targetOpen($open, $target);
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;
        $resistance = $success === 0
            ? $this->amounts->zero()
            : $this->resolution->resistanceOf(
                $game,
                $targetOpen,
                $this->resolution->resistanceChoice($choice, $defenderRoll),
                $success,
                $penetrationFactory(),
            );
        $soak = $choice['reaction'] === 'dodge'
            ? $this->resolution->soakOf($game, $targetOpen)
            : null;

        return [
            'effectiveDamage' => $effectiveDamage,
            'resistance' => $resistance,
            'soak' => $soak,
            'injury' => $this->amounts->injury($effectiveDamage, $resistance, $success, $soak),
        ];
    }

    /**
     * Пишет повреждение и последствия цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param DimensionalNumber $injury Повреждение.
     * @param int $success Успех.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     * @param array<string, mixed> $record Итог цели.
     *
     * @return int Версия листа.
     */
    private function writeTarget(
        GameRecord $game,
        array $open,
        array $target,
        array $choice,
        DimensionalNumber $injury,
        int $success,
        array $spends,
        int $actorUserId,
        bool $viewAll,
        array &$record,
    ): int {
        $prepared = $this->resolution->prepareSheet(
            $game,
            GameStrikeTargetRow::targetOpen($open, $target),
            $injury,
            $success,
        );
        $record = array_merge($record, $prepared['consequence']);

        return $this->sheetWrites->write(
            $game,
            $target['type'],
            $target['id'],
            $choice['expectedSheetVersion'],
            $prepared['operations'],
            $target,
            fn (
                CharacterConflictException|GameEconomyConflictException $exception,
            ): array => $this->sheets->projectWriteConflict(
                $game,
                $target,
                $exception,
                $actorUserId,
                $viewAll,
            ),
            $spends,
        );
    }

    /**
     * Бросок атакующего с помехой широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param GameBattleRecord $battle Бой.
     * @param CharacterRuleSlice $slice Срез.
     * @param int $targetCount Число целей.
     * @param array<int, string> $targetKeys Ключи целей.
     *
     * @return array<string, mixed> Roll.
     */
    public function rollOf(
        GameRecord $game,
        array $open,
        GameBattleRecord $battle,
        CharacterRuleSlice $slice,
        int $targetCount,
        array $targetKeys = [],
    ): array {
        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $advantages = [];
        $wideAdvantage = $this->rules->wideTargetCheckAdvantage(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $actionRuleCode,
            $targetCount,
        );
        if ($wideAdvantage !== null) {
            $advantages[] = $wideAdvantage;
        }

        return $this->resolution->rollOf(
            $game,
            $open,
            $hitCode,
            $battle->getPendingActionEffects(),
            $slice,
            $advantages,
            $targetKeys,
        );
    }
}
