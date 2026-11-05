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
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
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
     * Цикл жетонов после расчёта.
     *
     * @var array<string, mixed>
     */
    private array $cycleAfter = [];

    /**
     * Тратил ли хоть один защитник жетон концентрации.
     *
     * @var bool
     */
    private bool $tokenUsed = false;

    /**
     * Ключи меток, чьи защиты реально применены.
     *
     * @var array<int, string>
     */
    private array $markKeys = [];

    private readonly GameBattleMarks $marks;

    /**
     * Начинает расчёт с карты боя.
     *
     * @param array<string, mixed> $pending Карта.
     * @param array<string, mixed> $cycle Цикл жетонов.
     *
     * @return void
     */
    public function begin(array $pending, array $cycle = []): void
    {
        $this->pendingAfter = $pending;
        $this->cycleAfter = $cycle;
        $this->tokenUsed = false;
        $this->markKeys = [];
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
     * Цикл жетонов после расчёта.
     *
     * @return array<string, mixed> Карта.
     */
    public function cycleAfter(): array
    {
        return $this->cycleAfter;
    }

    /**
     * Был ли потрачен жетон концентрации.
     *
     * @return bool Признак.
     */
    public function tokenUsed(): bool
    {
        return $this->tokenUsed;
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
     * Создаёт расчёт цели.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Расчёт бросков и урона.
     * @param GameStrikeSpend $spend Траты реакции.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeCover $cover Броски прикрытия.
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
        private readonly GameStrikeCover $cover,
    ) {
        $this->marks = new GameBattleMarks();
    }

    /**
     * Готовит реакцию цели и броски прикрывающих до применения.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка цели.
     * @param array<string, mixed> $choice Защита.
     * @param string $hitCode Код проверки.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $profileType Профиль.
     * @param bool $isPush Толчок.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     *
     * @return array{reaction: array<string, mixed>, cover: array<string, mixed>|null} Подготовка.
     */
    public function prepare(
        GameRecord $game,
        array $open,
        array $row,
        array $choice,
        string $hitCode,
        array $attackerRoll,
        string $profileType,
        bool $isPush,
        ?DimensionalNumber $shotDistance = null,
        array $marks = [],
    ): array {
        $target = GameStrikeTargetRow::defender($row);
        $reaction = $this->reactionOf(
            $game,
            $target,
            $choice,
            GameStrikeTargetRow::conditions($row),
            $hitCode,
        );
        $cover = null;
        if (!$reaction['insufficient'] && $this->jsonRows($row, 'coverers') !== []) {
            $cover = $this->cover->resolve(
                $game,
                $open,
                $row,
                GameStrikeTargetRow::conditions($row),
                $choice,
                $attackerRoll,
                $hitCode,
                $reaction['slice'] ?? $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
                $this->pendingAfter,
                $reaction['pending'],
                $profileType,
                $isPush,
                $shotDistance,
                is_int($choice['expectedSheetVersion'] ?? null) ? $choice['expectedSheetVersion'] : 0,
                $marks,
            );
        }

        return ['reaction' => $reaction, 'cover' => $cover];
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
     * @param array<int, RollAdvantage> $extraAdvantages Преимущества защиты из поддержек.
     * @param array<string, mixed>|null $prepared Реакция и прикрытие строки.
     * @param array<string, mixed>|null $resolved Готовая защита без переброса.
     * @param array<int, array<string, mixed>> $marks Метки боя.
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
        array $extraAdvantages = [],
        ?array $prepared = null,
        ?array $resolved = null,
        array $marks = [],
    ): array {
        $target = GameStrikeTargetRow::defender($row);
        if ($refusalCode !== null) {
            return GameStrikeTargetRow::row($target, $refusalCode, null, null, null);
        }

        if ($resolved !== null) {
            $choice = $resolved['choice'];
            $attackerRoll = $resolved['attackerRoll'];
            $reaction = [
                'spends' => [],
                'pending' => $this->pendingAfter,
                'slice' => $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
                'reactionRuleCode' => $resolved['reactionRuleCode'],
                'insufficient' => false,
                'token' => null,
            ];
        } elseif ($prepared !== null) {
            $reaction = $prepared['reaction'];
        } else {
            $reaction = $this->reactionOf(
                $game,
                $target,
                $choice,
                GameStrikeTargetRow::conditions($row),
                $hitCode,
            );
        }

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
        $markDefense = ['advantages' => [], 'keys' => []];
        if ($resolved === null && in_array($choice['reaction'] ?? null, ['dodge', 'block'], true)) {
            $attacker = $this->attackerOf($open);
            if ($attacker !== null) {
                $markDefense = $this->marks->forDefense($marks, $target, $attacker);
            }
        }

        $defense = $resolved !== null
            ? [
                'defenderRoll' => $resolved['defenderRoll'],
                'autoFail' => $this->rules->isHitAutoFail($attackerRoll['roll']),
                'success' => $this->resolution->successOf(
                    $attackerRoll,
                    $resolved['defenderRoll'],
                    is_string($choice['reaction'] ?? null) ? $choice['reaction'] : 'ignore',
                ),
            ]
            : $this->defenseOf(
                $game,
                $open,
                $target,
                $choice,
                $attackerRoll,
                $reaction['pending'],
                $reactionRuleCode,
                $slice,
                $hitCode,
                GameStrikeTargetRow::conditions($row),
                $reaction['token']['advantage'] ?? null,
                array_merge($extraAdvantages, $markDefense['advantages']),
            );
        $appliedMarks = $resolved !== null
            ? $this->stringList($resolved['markKeys'] ?? null)
            : $markDefense['keys'];
        if ($appliedMarks !== []) {
            $this->markKeys = array_merge($this->markKeys, $appliedMarks);
        }

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
            // Широкий удар — одна проверка на несколько целей: хвост «Стремительного» не применяется.
            [],
            $attackerRoll,
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
        $record['appliedSr'] = $outcome['appliedSr'];
        $record['srCap'] = $outcome['srCap'];
        if ($outcome['durabilityShave'] > 0) {
            $record['durabilityShave'] = $outcome['durabilityShave'];
        }

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

        if ($reactionRuleCode !== null) {
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
     * @param array<string, mixed> $conditions Условия цели.
     * @param string $hitCode Карточка проверки попадания.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>,
     *               slice: CharacterRuleSlice|null, reactionRuleCode: string|null, insufficient: bool,
     *               token: array{spend: ResourceSpend, advantage: RollAdvantage, cycle: array<string, mixed>}|null}
     */
    private function reactionOf(
        GameRecord $game,
        array $target,
        array $choice,
        array $conditions,
        string $hitCode,
    ): array {
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

        if (($conditions['turn'] ?? false) === true) {
            if ($slice === null) {
                throw new GameInvalidException('Game wide strike turn is invalid');
            }

            $turnCode = $this->rules->reactionRuleCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                'turn',
            );
            if ($turnCode === null) {
                throw new GameInvalidException('Game wide strike turn action is missing');
            }

            $spends = array_merge(
                $spends,
                $this->rules->resolveReactionResourceSpends(
                    $game->getSpaceId(),
                    $game->getRulesRevision(),
                    'turn',
                    null,
                    '',
                    $this->choices->targetChoices($game, $target),
                    $this->sheets->getTargetSheet($game, $target),
                ),
            );
        }

        $token = $choice['reaction'] === 'ignore'
            ? null
            : $this->spend->defenderToken(
                $game,
                $choice,
                $this->sheets->getTargetSheet($game, $target),
                $hitCode,
                $target['type'] . ':' . $target['id'],
                $this->cycleAfter,
            );
        if ($token !== null) {
            $spends[] = $token['spend'];
        }

        $insufficient = $spends !== [] && !$this->rules->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->sheets->getTargetSheet($game, $target),
            $spends,
        );
        if (!$insufficient && $token !== null) {
            $this->cycleAfter = $token['cycle'];
            $this->tokenUsed = true;
        }

        return [
            'spends' => $spends,
            'pending' => $pending,
            'slice' => $slice,
            'reactionRuleCode' => $reactionRuleCode,
            'insufficient' => $insufficient,
            'token' => $insufficient ? null : $token,
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
     * @param array<string, mixed> $conditions Условия цели.
     * @param RollAdvantage|null $token Преимущество жетона концентрации.
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
        array $conditions,
        ?RollAdvantage $token = null,
        array $extraAdvantages = [],
    ): array {
        $targetOpen = GameStrikeTargetRow::targetOpen($open, $target);
        $defenderRoll = null;
        $ratedRoll = $attackerRoll;
        $flank = ($conditions['flank'] ?? false) === true && ($conditions['turn'] ?? false) !== true
            ? [new RollAdvantage('flank', -2, AdvantageSourceLabels::getLabel('flank'))]
            : [];
        if ($token !== null) {
            $flank[] = $token;
        }

        array_push($flank, ...$extraAdvantages);

        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->resolution->dodgeRollOf(
                $game,
                $targetOpen,
                $hitCode,
                $attackerRoll,
                $pending,
                $reactionRuleCode,
                $slice ?? $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
                $flank,
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
                $flank,
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
     * @param array<string, mixed> $pending Карта ожидания атакующего.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     *
     * @return array{effectiveDamage: DimensionalNumber, resistance: DimensionalNumber,
     *               soak: DimensionalNumber|null, durabilityShave: int, injury: DimensionalNumber,
     *               appliedSr: int, srCap: int|null}
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
        array $pending,
        array $attackerRoll,
    ): array {
        $targetOpen = GameStrikeTargetRow::targetOpen($open, $target);
        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $durabilityShave = $success === 0
            ? 0
            : $this->rules->durabilityShave(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $actionRuleCode,
                is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '',
                is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '',
                is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0,
                $attackerRoll,
                $success,
            );
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;
        $resistance = $success === 0
            ? $this->amounts->zero()
            : $this->resolution->resistanceOf(
                $game,
                $targetOpen,
                $this->resolution->resistanceChoice($choice, $defenderRoll),
                $success,
                $penetrationFactory(),
                $durabilityShave,
            );
        $soak = $this->resolution->soakOf($game, $targetOpen, $choice['reaction'], $success, $pending);
        $damageTypeCode = $this->rules->damageTypeCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '',
            is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '',
            is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0,
        );
        $srCap = $this->rules->successRatingCap($game->getSpaceId(), $game->getRulesRevision(), $damageTypeCode);
        $appliedSr = $this->amounts->appliedSr($success, $srCap);

        return [
            'effectiveDamage' => $effectiveDamage,
            'resistance' => $resistance,
            'soak' => $soak,
            'durabilityShave' => $durabilityShave,
            'injury' => $this->amounts->injury($effectiveDamage, $resistance, $appliedSr, $soak),
            'appliedSr' => $appliedSr,
            'srCap' => $srCap,
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
     * @param array<int, RollAdvantage> $extraAdvantages Преимущества атакующего из поддержек.
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
        array $extraAdvantages = [],
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

        array_push($advantages, ...$extraAdvantages);

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
