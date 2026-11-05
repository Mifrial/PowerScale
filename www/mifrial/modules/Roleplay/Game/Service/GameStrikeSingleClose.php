<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;
use Mifrial\Roleplay\Rule\Spec\AbilityCombat;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Закрытие одиночного удара.
 */
final class GameStrikeSingleClose
{
    /**
     * Создаёт закрытие.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameBattleRepository $battles Бои.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Расчёт.
     * @param GameStrikeSpend $spend Траты.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeSingleChat $chat Чат.
     * @param GameActionModifierRunner $modifiers Модификаторы действия.
     * @param GamePushRules $push Толчок.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameStrikeSingleChat $chat,
        private readonly GameActionModifierRunner $modifiers,
        private readonly GamePushRules $push,
        private readonly GameStateIncreaseChecks $stateChecks,
        private readonly GameProcessFlow $processes,
    ) {
    }

    /**
     * Тело закрытия одиночного удара.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $requestChoice Защита запроса.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     * @param array<string, mixed>|null $resume Решение окна фактической цели.
     *
     * @return array<string, mixed> Итог.
     */
    public function close(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $requestChoice,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        ?array $resume = null,
    ): array {
        $open = $resume !== null
            ? $this->rows->requireOpen($battleId, 'strike')
            : $this->rows->openSingle($battleId, $game, $actorUserId, $editAll);
        $battle = $this->rows->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $pushSpec = $this->push->pushOf($game, $actionRuleCode);
        $isPush = $pushSpec !== null;

        $sequential = $this->rules->isSequentialStrike(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $actionRuleCode,
        );
        $activeIndex = 0;
        if ($sequential) {
            foreach ($strikes as $index => $strikeRow) {
                if (($strikeRow['reaction'] ?? null) === null) {
                    $activeIndex = $index;
                    break;
                }
            }
        }

        $row = is_array($strikes[$activeIndex] ?? null) ? $strikes[$activeIndex] : null;
        if ($row === null) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $resumeState = null;
        if ($resume !== null) {
            $resumeState = $this->resumeState($row, $resume);
            $row = $resumeState['row'];
            $expectedSheetVersion = $resumeState['expectedSheetVersion'];
        }

        $open = array_merge($open, [
            'item_inventory_id' => $row['item_inventory_id'] ?? null,
            'item_rule_code' => $row['item_rule_code'] ?? null,
            'profile_type' => $row['profile_type'] ?? null,
            'profile_index' => $row['profile_index'] ?? null,
            'conditions' => GameStrikeTargetRow::conditions($row),
        ]);
        $packets = $sequential ? 1 : max(1, count($strikes));
        $target = $this->rows->defender($row);
        $targetKey = $this->rows->targetKey($target);
        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $process = $this->processes->strike(
            $game,
            $open,
            $this->abilityLevels($game, $attackerKind, $attackerId),
        );

        $lastStrike = new GameLastStrikeSnapshot();
        if ($resumeState === null) {
            $this->choices->assertReaction($game, $target, $requestChoice);
            $choice = $this->authoritativeChoice($game, $target, $requestChoice);
        } else {
            $choice = $resumeState['choice'];
        }

        $turn = ($choice['turn'] ?? false) === true;
        $conditions = $open['conditions'];
        if ($turn && (($conditions['flank'] ?? false) !== true || $choice['reaction'] === 'ignore')) {
            throw new GameInvalidException('Game strike turn is invalid');
        }

        if ($isPush && ($turn || $sequential || $packets > 1)) {
            throw new GameInvalidException('Game push reaction is invalid');
        }

        if ($isPush && $choice['reaction'] === 'block') {
            $blockItem = is_string($choice['blockItemRuleCode'] ?? null) ? $choice['blockItemRuleCode'] : '';
            $this->rules->assertPushBlock(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $actionRuleCode,
                $blockItem,
            );
        }

        if ($turn) {
            $conditions = (new GameStrikeConditions())->withTurn($conditions, true);
        }

        $targetOpen = $this->rows->targetOpen($open, $target);
        $this->sheets->assertDefenderSheet(
            $game,
            $targetOpen,
            $expectedSheetVersion,
            $actorUserId,
            $viewAll,
        );

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $row = $this->spendDeclaredReactions($game, $slice, $row);
        $profileType = is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '';
        $profileIndex = is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0;
        $itemInventoryId = is_int($open['item_inventory_id'] ?? null) ? $open['item_inventory_id'] : 0;
        $itemRuleCode = is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '';
        $attackerChoices = $this->choices->attackerChoices($game, $open);
        $attackContext = $this->rules->attackFilterContext(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $attackerChoices,
            $itemInventoryId,
        );
        $modifier = $this->rules->actionCharacteristicModifier(
            $slice,
            $actionRuleCode,
            $profileType,
            1,
            $attackContext,
            $this->abilityLevels($game, $attackerKind, $attackerId),
        );
        $npcSheet = $attackerKind === 'npc'
            ? $this->sheets->getNpcSheet($game->getId(), $attackerId)
            : null;
        $npcChoices = $attackerKind === 'npc'
            ? $this->sheets->getNpcChoices($game->getId(), $attackerId)
            : null;
        $npcVersion = $attackerKind === 'npc'
            ? $this->sheets->getNpcVersion($game->getId(), $attackerId)
            : null;
        $metrics = $this->rules->distanceMetrics(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $attackerKind,
            $attackerId,
            $npcSheet,
            $npcChoices,
            $npcVersion,
            $conditions,
            $modifier,
            $actionRuleCode,
        );
        $hitCode = $isPush
            ? $this->push->checkCode($pushSpec)
            : $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $pending = $battle->getPendingActionEffects();
        $defenderPending = $pending;
        $reactionRuleCode = $choice['reaction'] === 'ignore'
            ? null
            : $this->rules->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $choice['reaction']);
        $turnRuleCode = $turn
            ? $this->rules->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), 'turn')
            : null;
        if ($turn && $turnRuleCode === null) {
            throw new GameInvalidException('Game strike turn action is missing');
        }

        $defenderChoices = [];
        if ($reactionRuleCode !== null || $choice['reaction'] === 'block' || $turn) {
            $defenderChoices = $this->choices->targetChoices($game, $target);
        }

        $spends = $choice['reaction'] === 'ignore'
            ? []
            : $this->spend->getReactionSpends(
                $game,
                $choice,
                $defenderChoices,
                $this->sheets->getTargetSheet($game, $target),
            );
        if ($reactionRuleCode !== null) {
            $declared = (new GamePendingActionCosts())->declare(
                $slice,
                $defenderChoices,
                $reactionRuleCode,
                $target['type'],
                $target['id'],
                $defenderPending,
                $this->spend->getSpendAmounts($spends),
                false,
            );
            $spends = $this->spend->getSpendRows($declared['amounts']);
            $defenderPending = $declared['pending'];
            $defenderKey = $target['type'] . ':' . $target['id'];
            if (array_key_exists($defenderKey, $defenderPending)) {
                $pending[$defenderKey] = $defenderPending[$defenderKey];
            } else {
                unset($pending[$defenderKey]);
            }
        }

        if ($turnRuleCode !== null) {
            $turnSpends = $this->rules->resolveReactionResourceSpends(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                'turn',
                null,
                '',
                $defenderChoices,
                $this->sheets->getTargetSheet($game, $target),
            );
            $spends = array_merge($spends, $turnSpends);
        }

        $defenderToken = $choice['reaction'] === 'ignore'
            ? null
            : $this->spend->defenderToken(
                $game,
                $choice,
                $this->sheets->getTargetSheet($game, $target),
                $hitCode,
                $target['type'] . ':' . $target['id'],
                $battle->getConcentrationCycle(),
            );
        if ($defenderToken !== null) {
            $spends[] = $defenderToken['spend'];
        }

        if ($resumeState !== null && $resumeState['prepaid']) {
            $spends = [];
            $defenderToken = null;
        }

        $previousSr = $lastStrike->previousSr($pending, $attackerKind, $attackerId, $targetKey);
        $supports = $this->supportAdvantages($row);
        $advantages = $supports['attacker'];
        $token = $this->tokenAdvantage($row, $slice);
        if ($token !== null) {
            $advantages[] = $token;
        }

        $tokenAccuracy = $this->tokenAccuracy($row);
        $strikePackage = $this->processes->strikePackage($process);
        if ($strikePackage['advantage'] !== 0) {
            $advantages[] = new RollAdvantage(
                'action',
                $strikePackage['advantage'],
                AdvantageSourceLabels::getLabel('action'),
            );
        }

        $repeatUses = is_array($process) && is_int($process['prior'] ?? null) ? $process['prior'] : 0;
        if ($repeatUses > 0) {
            $advantages[] = new RollAdvantage(
                'circumstances',
                -$repeatUses,
                AdvantageSourceLabels::getLabel('circumstances'),
            );
        }

        $flank = ($conditions['flank'] ?? false) === true && !$turn
            ? new RollAdvantage('flank', -2, AdvantageSourceLabels::getLabel('flank'))
            : null;
        if ($packets > 1) {
            $advantages[] = new RollAdvantage(
                'multi_attack',
                -$packets,
                AdvantageSourceLabels::getLabel('multi_attack'),
            );
        } elseif ($sequential) {
            $advantages[] = new RollAdvantage(
                'multi_attack',
                -1,
                AdvantageSourceLabels::getLabel('multi_attack'),
            );
        }

        $prepared = (new GamePreparedDefense())->modifier(
            $pending,
            $attackerKind,
            $attackerId,
            $targetKey,
            $choice['reaction'],
        );
        if ($prepared !== null) {
            $advantages[] = $prepared;
        }

        $defenderRoll = null;
        $pushChecks = [];
        $attackerRoll = $resumeState !== null
            ? $resumeState['attackerRoll']
            : $this->resolution->rollOf(
                $game,
                $open,
                $hitCode,
                $pending,
                $slice,
                $advantages,
                [$targetKey],
                $tokenAccuracy,
            );
        $defenseRolled = $resumeState !== null;
        if ($resumeState !== null) {
            $defenderRoll = $resumeState['defenderRoll'];
        }

        $coverers = $this->jsonRows($row, 'coverers');
        $coverInfo = null;
        if ($resumeState !== null && is_array($resumeState['cover'] ?? null)) {
            $coverInfo = $resumeState['cover'];
        }

        if ($coverers !== [] && $resumeState === null) {
            $cover = $this->resolveCover(
                $game,
                $open,
                $row,
                $conditions,
                $choice,
                $attackerRoll,
                $hitCode,
                $slice,
                $pending,
                $defenderPending,
                $profileType,
                $isPush,
                $profileType === 'shoot' ? $metrics['distance'] : null,
                $expectedSheetVersion,
            );
            if ($cover['winner'] !== null) {
                $primaryTarget = $target;
                $primaryKey = $this->rows->targetKey($primaryTarget);
                $winnerKey = $this->rows->targetKey($cover['winner']['actor']);
                $coverInfo = $this->coverInfoOf($cover['candidates'], $primaryKey, $winnerKey);
                if ($spends !== []) {
                    $this->sheetWrites->writeResources(
                        $game,
                        $target['type'],
                        $target['id'],
                        $expectedSheetVersion,
                        $spends,
                    );
                }

                $target = $cover['winner']['actor'];
                $targetKey = $this->rows->targetKey($target);
                $choice = $cover['winner']['choice'];
                $expectedSheetVersion = $cover['winner']['expectedSheetVersion'];
                $reactionRuleCode = $cover['winner']['reactionRuleCode'];
                $turnRuleCode = null;
                $defenderChoices = $this->choices->targetChoices($game, $target);
                $spends = $cover['winner']['spends'];
                $defenderPending = $pending;
                $defenderToken = null;
                $targetOpen = $this->rows->targetOpen($open, $target);
                $attackerRoll = $cover['winner']['attackerRoll'];
                $defenderRoll = $cover['winner']['defenderRoll'];
                $defenseRolled = true;
            } elseif ($cover['failed']) {
                return $this->pauseForTargetChoice(
                    $game,
                    $battle,
                    $target,
                    $spends,
                    $strikeId,
                    is_int($row['id'] ?? null) ? $row['id'] : $strikeId,
                    $expectedBattleVersion,
                    $attackerRoll,
                    $cover,
                    $choice,
                    $expectedSheetVersion,
                    $attackerKind,
                    $attackerId,
                );
            }
        }

        if (
            $choice['reaction'] !== 'ignore'
            && $this->rules->isUnconscious(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->sheets->getTargetSheet($game, $target),
            )
        ) {
            throw new GameInvalidException('Game strike defender is unconscious');
        }

        $defenderAdvantages = $supports['defender'];
        if ($flank !== null) {
            $defenderAdvantages[] = $flank;
        }

        if ($defenderToken !== null) {
            $defenderAdvantages[] = $defenderToken['advantage'];
        }

        if (!$defenseRolled && $choice['reaction'] === 'dodge') {
            $shotDistance = $profileType === 'shoot' ? $metrics['distance'] : null;
            $defenderRoll = $this->resolution->dodgeRollOf(
                $game,
                $targetOpen,
                $hitCode,
                $attackerRoll,
                $defenderPending,
                $reactionRuleCode,
                $slice,
                $defenderAdvantages,
                $shotDistance,
            );
            $attackerRoll = $profileType === 'shoot' || $profileType === 'throw'
                ? $this->resolution->rerateRangedHit($game, $open, $conditions, $attackerRoll, $defenderRoll, 'dodge')
                : $this->rules->rerateHit($attackerRoll, $defenderRoll);
        }

        if (!$defenseRolled && $choice['reaction'] === 'block') {
            $defenderRoll = $this->resolution->blockRollOf(
                $game,
                $targetOpen,
                $choice,
                $hitCode,
                $attackerRoll,
                $defenderPending,
                $reactionRuleCode,
                $slice,
                $defenderAdvantages,
            );
            if ($isPush) {
                $attackerRoll = $this->rules->rerateHit($attackerRoll, $defenderRoll);
            } elseif ($profileType === 'shoot' || $profileType === 'throw') {
                $attackerRoll = $this->resolution->rerateRangedHit(
                    $game,
                    $open,
                    $conditions,
                    $attackerRoll,
                    $defenderRoll,
                    'block',
                );
            }
        }

        if (
            !$defenseRolled
            && $choice['reaction'] === 'ignore'
            && ($profileType === 'shoot' || $profileType === 'throw')
        ) {
            $attackerRoll = $this->resolution->rerateRangedHit(
                $game,
                $open,
                $conditions,
                $attackerRoll,
                null,
                'ignore',
            );
        }

        $damageTypeCode = $isPush
            ? $this->rules->pushDamageTypeCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $itemRuleCode,
                $profileIndex,
            )
            : $this->rules->damageTypeCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $itemRuleCode,
                $profileType,
                $profileIndex,
            );
        if (
            $choice['reaction'] !== 'ignore'
            && $spends !== []
            && !$this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->sheets->getTargetSheet($game, $target),
                $spends,
            )
        ) {
            return $this->resourceIgnoredResult(
                $battle,
                $strikeId,
                $expectedBattleVersion,
                $attackerRoll,
                $defenderRoll,
            );
        }

        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $pushRating = $isPush
            ? ($autoFail ? 0 : (is_int($attackerRoll['rating'] ?? null) ? $attackerRoll['rating'] : 0))
            : null;
        $success = $isPush
            ? $pushRating
            : ($autoFail
                ? 0
                : $lastStrike->boostSuccess(
                    $slice,
                    $actionRuleCode,
                    $profileType === 'shoot' || $profileType === 'throw'
                        ? $this->resolution->successOfRanged($attackerRoll)
                        : $this->resolution->successOf($attackerRoll, $defenderRoll, $choice['reaction']),
                    $previousSr,
                ));
        if (!$isPush && $success > 0 && $strikePackage['extraSuccesses'] > 0) {
            $bonusSuccesses = $strikePackage['extraSuccesses'];
            $bonusRating = $this->rules->processBonusRating($attackerRoll, $bonusSuccesses);
            $success += $bonusRating;
            $attackerRoll['rating'] = (is_int($attackerRoll['rating'] ?? null) ? $attackerRoll['rating'] : 0)
                + $bonusRating;
            $attackerRoll['process_bonus'] = [
                'successes' => $bonusSuccesses,
                'rating' => $bonusRating,
            ];
        }

        $srCap = $this->rules->successRatingCap($game->getSpaceId(), $game->getRulesRevision(), $damageTypeCode);
        $pushCanceled = $isPush && ($autoFail || ($pushRating <= 0 && $choice['reaction'] !== 'block'));
        $damageSr = $isPush
            ? ($pushCanceled ? 0 : ($pushRating > 0 ? $this->amounts->appliedSr($pushRating, $srCap) : 1))
            : $this->amounts->appliedSr($success, $srCap);
        $damage = $autoFail
            ? $this->amounts->zero()
            : $this->resolution->damageOf(
                $game,
                $open,
                $isPush ? $damageSr : $success,
                1,
                $metrics['drop'],
                $isPush ? $this->push->damageModifier($pushSpec) : null,
            );
        $result = $this->closeResult(
            $battle,
            $strikeId,
            $expectedBattleVersion,
            $attackerRoll,
            $defenderRoll,
            $isPush ? $damageSr : $success,
            $damage,
        );
        $result['appliedSr'] = $damageSr;
        $result['srCap'] = $srCap;
        $durabilityShave = $damageSr === 0
            ? 0
            : $this->rules->durabilityShave(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $actionRuleCode,
                is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '',
                is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '',
                is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0,
                $attackerRoll,
                $damageSr,
            );
        $penetration = $damageSr === 0
            ? $this->amounts->zero()
            : $this->resolution->penetrationOf($game, $open, $damageSr, 1, $metrics['drop']);
        $resistance = $damageSr === 0
            ? $this->amounts->zero()
            : $this->resolution->resistanceOf(
                $game,
                $targetOpen,
                $this->resolution->resistanceChoice($choice, $result['defenderRoll'] ?? null),
                $damageSr,
                $penetration,
                $durabilityShave,
            );
        $soak = $this->resolution->soakOf($game, $targetOpen, $choice['reaction'], $damageSr, $pending);
        $result['resistance'] = $this->amounts->view($resistance);
        if ($soak !== null) {
            $result['S'] = $this->amounts->view($soak);
        }

        if ($durabilityShave > 0) {
            $result['durabilityShave'] = $durabilityShave;
        }

        $packetDamages = [];
        $packetInjuries = [];
        for ($packet = 1; $packet <= $packets; ++$packet) {
            $packetDamage = $autoFail
                ? $this->amounts->zero()
                : $this->resolution->damageOf(
                    $game,
                    $open,
                    $isPush ? $damageSr : $success,
                    $packet,
                    $metrics['drop'],
                    $isPush ? $this->push->damageModifier($pushSpec) : null,
                );
            $packetDamages[] = $packetDamage;
            $packetInjuries[] = $this->amounts->injury(
                $damageSr === 0 ? $this->amounts->zero() : $packetDamage,
                $resistance,
                $damageSr,
                $soak,
            );
        }

        $injury = $packetInjuries[0];
        $result['damage'] = $this->amounts->view($packetDamages[0]);
        $result['injury'] = $this->amounts->view($injury);
        $result['packetDamages'] = array_map($this->amounts->view(...), $packetDamages);
        $result['packetInjuries'] = array_map($this->amounts->view(...), $packetInjuries);
        $prepared = ($isPush ? $damageSr > 0 : !$autoFail)
            ? $this->resolution->prepareSheet(
                $game,
                $targetOpen,
                $this->amounts->sum($packetInjuries),
                $damageSr,
            )
            : null;
        if ($isPush) {
            $attackerSheet = $this->sheets->getTargetSheet($game, ['type' => $attackerKind, 'id' => $attackerId]);
            $defenderSheet = $this->sheets->getTargetSheet($game, $target);
            $floor = $this->push->stabilityFloor($defenderSheet);
            $attackerStrength = $this->rules->characteristicOf($attackerSheet, 'strength');
            $defenderStrength = $this->rules->characteristicOf($defenderSheet, 'strength');
            $stability = $this->push->stability(
                $pushSpec,
                $choice['reaction'],
                $pushRating,
                $defenderStrength,
                $floor,
            );
            $unstable = $pushCanceled
                ? 0
                : $this->push->unstable($pushSpec, $attackerStrength, $stability, $damageTypeCode);
            $unstableCode = $unstable > 0 ? $this->push->unstableCode($game) : null;
            if ($unstableCode !== null && is_array($prepared)) {
                $prepared['operations'][] = [
                    'kind' => 'putState',
                    'stateRuleCode' => $unstableCode,
                    'value' => $unstable,
                ];
                $snapshot = $this->sheets->snapshot($game, $target['type'], $target['id']);
                $document = [
                    'rulesRevision' => $game->getRulesRevision(),
                    'choices' => is_array($snapshot['sheet']['choices'] ?? null) ? $snapshot['sheet']['choices'] : [],
                    'sheet' => is_array($snapshot['sheet']['sheet'] ?? null) ? $snapshot['sheet']['sheet'] : [],
                ];
                $preview = $this->sheetWrites->previewDocument($game, $document, $prepared['operations']);
                $planned = $this->stateChecks->plan(
                    $game,
                    $document['sheet'],
                    $preview['rulesRevision'],
                    $preview['states'],
                    $prepared['operations'],
                    [$unstableCode],
                );
                $prepared['operations'] = $planned['operations'];
                foreach ($planned['rolls'] as $entry) {
                    $pushChecks[] = $entry;
                }
            }

            $knockback = $this->push->knockback($unstable);
            $result['push'] = [
                'rating' => $pushRating,
                'stability' => $this->amounts->view($stability),
                'unstable' => $unstable,
                'unstableCode' => $unstableCode,
                'knockback' => $knockback instanceof DimensionalNumber
                    ? $this->amounts->view($knockback)
                    : null,
                'canceled' => $pushCanceled,
            ];
        }

        if (is_array($prepared)) {
            $result = array_merge($result, $prepared['consequence']);
        }

        $noEffects = $isPush
            ? $prepared === null || $prepared['operations'] === []
            : $autoFail;
        $result['sheetVersion'] = $noEffects && $spends === []
            ? null
            : ($noEffects
                ? $this->sheetWrites->writeResources(
                    $game,
                    $target['type'],
                    $target['id'],
                    $expectedSheetVersion,
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
                : $this->sheetWrites->write(
                    $game,
                    $target['type'],
                    $target['id'],
                    $expectedSheetVersion,
                    $prepared['operations'] ?? [],
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
                ));
        $targetOpen['conditions'] = $conditions;
        $this->chat->post(
            $game,
            $battle,
            $targetOpen,
            $actorUserId,
            $strikeId,
            $choice['reaction'],
            $result,
            null,
            $coverInfo,
        );
        if ($pushChecks !== []) {
            $this->chat->postChecks($game, $battle, $targetOpen, $actorUserId, $strikeId, $pushChecks);
        }

        for ($packet = 2; $packet <= $packets; ++$packet) {
            $packetResult = $result;
            $packetResult['injury'] = $result['packetInjuries'][$packet - 1] ?? $result['injury'];
            $packetResult['damage'] = $result['packetDamages'][$packet - 1] ?? $result['damage'];
            $this->chat->postPacket(
                $game,
                $battle,
                $targetOpen,
                $actorUserId,
                $strikeId,
                $packet,
                $packetResult,
            );
        }

        $answer = [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'],
            'block_item_profile_index' => $choice['blockItemProfileIndex'],
            'block_item_rule_code' => $choice['blockItemRuleCode'],
            'conditions' => $conditions,
            'resolution' => [],
        ];
        if ($sequential) {
            $this->actions->answerStrike(is_int($row['id'] ?? null) ? $row['id'] : 0, $answer);
        } else {
            $this->actions->closeWithAnswers($strikeId, $answer);
        }

        $remaining = $sequential ? $this->remainingStrikes($strikeId, $activeIndex, $choice['reaction']) : 0;
        if ($remaining > 0) {
            $pending = $lastStrike->store(
                $pending,
                $attackerKind,
                $attackerId,
                $actionRuleCode,
                $lastStrike->kindOf($slice, $actionRuleCode),
                [
                    [
                        'targetKey' => $targetKey,
                        'attackSr' => $result['success'],
                        'reaction' => $choice['reaction'],
                        'damaged' => $injury->toInteger() > 0,
                    ],
                ],
            );
            if ($defenderToken !== null) {
                $this->battles->replaceTracked(
                    $battleId,
                    $expectedBattleVersion,
                    $pending,
                    null,
                    null,
                    null,
                    $defenderToken['cycle'],
                );
            } else {
                $this->battles->replacePending($battleId, $expectedBattleVersion, $pending);
            }

            $result['closed'] = false;
            $result['sequential'] = true;
            $result['resolvedIndex'] = $activeIndex;

            return $result;
        }

        if ($sequential) {
            $this->actions->close($strikeId);
        }

        $skipAfterAction = false;
        $modifierCodes = $this->modifierCodes($row);
        if ($modifierCodes !== []) {
            $result['modifiers'] = $this->modifiers->perform(
                $game,
                $battle,
                $open,
                $strikeId,
                $actionRuleCode,
                $attackerKind,
                $attackerId,
                $modifierCodes,
                $modifier,
                $this->rules->gripBonus($attackContext['occupyHands']),
                $actorUserId,
            );
            $skipAfterAction = ($result['modifiers']['skipped'] ?? false) === true;
        }

        $pending = (new GamePendingActionCosts())->complete(
            $slice,
            $attackerChoices,
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $pending,
            [
                'action-points' => is_array($process) && is_int($process['actionPoints'] ?? null)
                    ? $process['actionPoints']
                    : $this->spend->getActionAp($slice, $actionRuleCode, true),
            ],
            [$targetKey],
            $skipAfterAction,
        );
        if (is_array($process)) {
            if (($process['closing'] ?? false) === true) {
                $pending = $this->processes->closeByAttack(
                    $game,
                    ['type' => $attackerKind, 'id' => $attackerId],
                    $process['row']->getId(),
                    $pending,
                );
            } else {
                $stepCode = $process['step']->getCode();
                $hitSuccess = $isPush ? !$autoFail : (!$autoFail && $success > 0);
                $pending = $this->processes->complete(
                    $game,
                    ['type' => $attackerKind, 'id' => $attackerId],
                    $process['row']->getId(),
                    $stepCode,
                    $hitSuccess,
                    $pending,
                    $process['weaponKey'],
                    $target,
                );
            }
        }

        if ($reactionRuleCode !== null) {
            $pending = (new GamePendingActionCosts())->complete(
                $slice,
                $defenderChoices,
                $reactionRuleCode,
                $target['type'],
                $target['id'],
                $pending,
                $this->spend->getSpendAmounts($spends),
            );
        }

        $pending = $lastStrike->store(
            $pending,
            $attackerKind,
            $attackerId,
            $actionRuleCode,
            $lastStrike->kindOf($slice, $actionRuleCode),
            [
                [
                    'targetKey' => $targetKey,
                    'attackSr' => $result['success'],
                    'reaction' => $choice['reaction'],
                    'damaged' => $injury->toInteger() > 0,
                ],
            ],
        );

        if ($defenderToken !== null) {
            $this->battles->replaceTracked(
                $battleId,
                $expectedBattleVersion,
                $pending,
                null,
                null,
                null,
                $defenderToken['cycle'],
            );
        } else {
            $this->battles->replacePending($battleId, $expectedBattleVersion, $pending);
        }

        $result['closed'] = true;
        $result['sequential'] = $sequential;
        $result['resolvedIndex'] = $activeIndex;

        return $result;
    }

    /**
     * Сколько ударов ещё ждут ответа.
     *
     * @param int $strikeId Действие.
     * @param int $activeIndex Только что отвеченный удар.
     * @param string $reaction Ответ.
     *
     * @return int Число.
     */
    private function remainingStrikes(int $strikeId, int $activeIndex, string $reaction): int
    {
        $remaining = 0;
        foreach ($this->actions->getStrikes($strikeId) as $index => $row) {
            $answered = $index === $activeIndex ? $reaction : ($row['reaction'] ?? null);

            if ($answered === null) {
                ++$remaining;
            }
        }

        return $remaining;
    }

    /**
     * Подменяет код блока кодом выбранного inventory instance.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return array<string, mixed> Authoritative выбор.
     */
    private function authoritativeChoice(GameRecord $game, array $target, array $choice): array
    {
        if ($choice['reaction'] !== 'block') {
            return $choice;
        }

        $inventoryId = $choice['blockItemInventoryId'] ?? null;
        if (!is_int($inventoryId)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        $choice['blockItemRuleCode'] = $this->rules->getBlockItemRuleCode(
            $this->choices->targetChoices($game, $target),
            $inventoryId,
        );

        return $choice;
    }

    /**
     * Формирует result envelope для automatic ignore.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Удар.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     *
     * @return array<string, mixed> Итог.
     */
    private function resourceIgnoredResult(
        GameBattleRecord $battle,
        int $strikeId,
        int $expectedBattleVersion,
        array $attackerRoll,
        ?array $defenderRoll,
    ): array {
        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion,
            'attackerRoll' => $attackerRoll,
            'defenderRoll' => $defenderRoll,
            'success' => 0,
            'damage' => $this->amounts->view($this->amounts->zero()),
            'resistance' => $this->amounts->view($this->amounts->zero()),
            'injury' => $this->amounts->view($this->amounts->zero()),
            'sheetVersion' => null,
        ];
    }

    /**
     * Базовый итог одиночного закрытия.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Удар.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     * @param int $success Готовый успех попадания.
     * @param DimensionalNumber $damage Урон.
     *
     * @return array<string, mixed> Итог.
     */
    private function closeResult(
        GameBattleRecord $battle,
        int $strikeId,
        int $expectedBattleVersion,
        array $attackerRoll,
        ?array $defenderRoll,
        int $success,
        DimensionalNumber $damage,
    ): array {
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;

        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion + 1,
            'attackerRoll' => $attackerRoll,
            'success' => $success,
            'damage' => $this->amounts->view($effectiveDamage),
            'resistance' => $this->amounts->view($this->amounts->zero()),
            'sheetVersion' => null,
        ];
        if ($defenderRoll !== null) {
            $result['defenderRoll'] = $defenderRoll;
        }

        return $result;
    }

    /**
     * Уровни способностей атакующего.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид актора.
     * @param int $actorId Идентификатор.
     *
     * @return array<string, int> Уровни.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function abilityLevels(GameRecord $game, string $kind, int $actorId): array
    {
        return $this->sheets->abilityLevels($game, $kind, $actorId);
    }

    /**
     * Поддержки, записанные в строку удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array{attacker: list<RollAdvantage>, defender: list<RollAdvantage>} Преимущества.
     */
    private function supportAdvantages(array $row): array
    {
        $raw = $row['supporters'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $supports = ['attacker' => [], 'defender' => []];
        if (!is_array($raw)) {
            return $supports;
        }

        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $side = $entry['side'] ?? null;
            $advantage = $entry['advantage'] ?? null;
            $source = $entry['source'] ?? null;
            if (
                ($side !== 'attacker' && $side !== 'defender')
                || !is_int($advantage)
                || $advantage === 0
                || !is_string($source)
                || $source === ''
            ) {
                continue;
            }

            $supports[$side][] = new RollAdvantage($source, $advantage, AdvantageSourceLabels::getLabel($source));
        }

        return $supports;
    }

    /**
     * Преимущество от жетонов концентрации.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param CharacterRuleSlice $slice Срез правил.
     *
     * @return RollAdvantage|null Преимущество.
     */
    private function tokenAdvantage(array $row, CharacterRuleSlice $slice): ?RollAdvantage
    {
        $raw = $row['token_spend'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw) || ($raw['mode'] ?? null) !== 'advantage') {
            return null;
        }

        $amount = $raw['amount'] ?? null;
        $source = $raw['source'] ?? null;
        if (!is_int($amount) || $amount < 1 || !is_string($source) || $source === '') {
            return null;
        }

        return new RollAdvantage($source, $amount, $slice->findLive($source)?->getName());
    }

    /**
     * Точность от жетонов карточки Расчётливой атаки.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return int Точность.
     */
    private function tokenAccuracy(array $row): int
    {
        $raw = $row['token_spend'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw) || ($raw['mode'] ?? null) !== 'accuracy') {
            return 0;
        }

        return is_int($raw['amount'] ?? null) && $raw['amount'] > 0 ? $raw['amount'] : 0;
    }

    /**
     * Объявленные модификаторы действия из строки.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return list<string> Коды.
     */
    private function modifierCodes(array $row): array
    {
        $raw = $row['action_modifiers'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        $codes = [];
        foreach ($raw as $code) {
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * JSON-список записей строки удара.
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

    /**
     * JSON-объект строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return array<string, mixed> Объект.
     */
    private function jsonRow(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Списывает заявки Поддержки и Прикрытия в момент старта реакции.
     *
     * Объявление ничего не списывает. Если ресурсов уже не хватает, заявка
     * снимается и не участвует в расчёте; устаревшая версия листа — конфликт.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array<string, mixed> Строка с обновлёнными заявками.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameBattleConflictException Если версия листа чужая.
     */
    private function spendDeclaredReactions(GameRecord $game, CharacterRuleSlice $slice, array $row): array
    {
        $turn = $this->spend->getTurnResourceCode($slice);
        if ($turn === null) {
            return $row;
        }

        $changed = false;
        foreach (['supporters', 'coverers'] as $key) {
            $entries = $this->jsonRows($row, $key);
            $kept = [];
            foreach ($entries as $entry) {
                if (($entry['spent'] ?? false) === true) {
                    $kept[] = $entry;
                    continue;
                }

                $expected = $entry['expectedSheetVersion'] ?? null;
                $actor = $entry['actor'] ?? null;
                if (
                    !is_int($expected)
                    || !is_array($actor)
                    || !is_string($actor['type'] ?? null)
                    || !is_int($actor['id'] ?? null)
                ) {
                    $kept[] = $entry;
                    continue;
                }

                $changed = true;
                $entry['spent'] = true;
                $amount = $key === 'supporters'
                    ? $this->spend->getActionAp($slice, is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '')
                    : (is_int($entry['cost'] ?? null) ? $entry['cost'] : 0);
                if ($amount <= 0) {
                    $kept[] = $entry;
                    continue;
                }

                $spends = [new ResourceSpend($turn, new ScalarResourceValue($amount))];
                $sheet = $this->sheets->getTargetSheet($game, $actor);
                if (
                    !$this->rules->hasSufficientResources(
                        $game->getSpaceId(),
                        $game->getRulesRevision(),
                        $sheet,
                        $spends,
                    )
                ) {
                    continue;
                }

                $entry['expectedSheetVersion'] = $this->sheetWrites->writeResources(
                    $game,
                    $actor['type'],
                    $actor['id'],
                    $expected,
                    $spends,
                );
                $kept[] = $entry;
            }

            if ($changed) {
                $row[$key] = $kept;
            }
        }

        if ($changed) {
            $strikeId = $row['id'] ?? null;
            if (is_int($strikeId)) {
                $this->actions->answerStrike($strikeId, [
                    'supporters' => $row['supporters'],
                    'coverers' => $row['coverers'],
                ]);
            }
        }

        return $row;
    }

    /**
     * Помехи защите прикрывающего из его карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Карточка прикрытия.
     *
     * @return array<int, RollAdvantage> Помехи.
     */
    private function coverAdvantages(CharacterRuleSlice $slice, string $ruleCode): array
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
     * @param array<int, RollAdvantage> $extraAdvantages Помехи защиты.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     *
     * @return array{attackerRoll: array<string, mixed>, defenderRoll: array<string, mixed>|null, passed: bool, rating: int} Защита.
     *
     * @throws GameInvalidException Если защита битая.
     */
    private function defenseCandidate(
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

        $reactionRuleCode = $this->rules->reactionRuleCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['reaction'],
        );
        $defenderRoll = null;
        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->resolution->dodgeRollOf(
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
                ? $this->resolution->rerateRangedHit($game, $open, $conditions, $attackerRoll, $defenderRoll, 'dodge')
                : $this->rules->rerateHit($attackerRoll, $defenderRoll);
        } elseif ($choice['reaction'] === 'block') {
            $defenderRoll = $this->resolution->blockRollOf(
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
                $attackerRoll = $this->rules->rerateHit($attackerRoll, $defenderRoll);
            } elseif ($profileType === 'shoot' || $profileType === 'throw') {
                $attackerRoll = $this->resolution->rerateRangedHit(
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
     *
     * @return array{
     *     winner: array<string, mixed>|null,
     *     failed: bool,
     *     candidates: array<string, array<string, mixed>>
     * } Итог.
     *
     * @throws GameInvalidException Если защита битая.
     */
    private function resolveCover(
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
    ): array {
        $target = $this->rows->defender($row);
        $targetOpen = $this->rows->targetOpen($open, $target);
        $candidates = [];
        $primaryKey = $this->rows->targetKey($target);
        $primary = $this->defenseCandidate(
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
            [],
            $shotDistance,
        );
        $candidates[$primaryKey] = [
            'actor' => $target,
            'choice' => $choice,
            'ruleCode' => '',
            'expectedSheetVersion' => $targetSheetVersion,
            'cost' => 0,
            'reactionRuleCode' => $choice['reaction'] === 'ignore'
                ? null
                : $this->rules->reactionRuleCode(
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
            $advantages = $this->coverAdvantages(
                $slice,
                is_string($entry['ruleCode'] ?? null) ? $entry['ruleCode'] : '',
            );
            $candidate = $this->defenseCandidate(
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
                $advantages,
            );
            $key = $this->rows->targetKey($actor);
            $cost = is_int($entry['cost'] ?? null) ? $entry['cost'] : 0;
            $reactionRuleCode = $this->rules->reactionRuleCode(
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
     * Останавливает удар и ждёт выбора фактической цели атакующим.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $target Исходная цель.
     * @param array<int, ResourceSpend> $spends Защита исходной цели.
     * @param int $strikeId Строка.
     * @param int $rowId Строка удара.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $cover Итог прикрытия.
     * @param array<string, mixed> $choice Защита исходной цели.
     * @param int $expectedSheetVersion Версия листа исходной цели.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     *
     * @return array<string, mixed> Итог с ожиданием.
     */
    private function pauseForTargetChoice(
        GameRecord $game,
        GameBattleRecord $battle,
        array $target,
        array $spends,
        int $strikeId,
        int $rowId,
        int $expectedBattleVersion,
        array $attackerRoll,
        array $cover,
        array $choice,
        int $expectedSheetVersion,
        string $attackerKind,
        int $attackerId,
    ): array {
        $primaryKey = $target['type'] . ':' . $target['id'];
        if ($spends !== []) {
            $expectedSheetVersion = $this->sheetWrites->writeResources(
                $game,
                $target['type'],
                $target['id'],
                $expectedSheetVersion,
                $spends,
            );
            $cover['candidates'][$primaryKey]['expectedSheetVersion'] = $expectedSheetVersion;
        }

        $options = [];
        foreach ($cover['candidates'] as $key => $candidate) {
            $options[] = ['key' => $key, 'actor' => $candidate['actor']];
        }

        $awaiting = [
            [
                'kind' => 'actual_target',
                'actor' => ['type' => $attackerKind, 'id' => $attackerId],
                'options' => $options,
            ],
        ];
        $resolution = [
            'attackerRoll' => $attackerRoll,
            'primaryKey' => $primaryKey,
            'candidates' => $cover['candidates'],
            'awaiting' => $awaiting,
        ];
        $this->actions->answerStrike($rowId, [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'],
            'block_item_profile_index' => $choice['blockItemProfileIndex'],
            'block_item_rule_code' => $choice['blockItemRuleCode'],
            'expected_sheet_version' => $expectedSheetVersion,
            'resolution' => $resolution,
        ]);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            null,
            null,
            null,
            null,
        );

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $saved->getStateVersion(),
            'closed' => false,
            'awaiting' => $awaiting,
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
    private function coverInfoOf(array $candidates, string $primaryKey, ?string $winnerKey): ?array
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
     * Восстанавливает состояние окна выбора фактической цели.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed> $resume Решение.
     *
     * @return array{
     *     row: array<string, mixed>,
     *     choice: array<string, mixed>,
     *     attackerRoll: array<string, mixed>,
     *     defenderRoll: array<string, mixed>|null,
     *     expectedSheetVersion: int,
     *     prepaid: bool,
     *     cover: array<string, mixed>|null
     * } Состояние.
     *
     * @throws GameInvalidException Если окно закрыто или выбор чужой.
     */
    private function resumeState(array $row, array $resume): array
    {
        $resolution = $this->jsonRow($row, 'resolution');
        $candidates = $resolution['candidates'] ?? null;
        $key = $resume['actualTarget'] ?? null;
        if (!is_array($candidates) || !is_string($key) || !is_array($candidates[$key] ?? null)) {
            throw new GameInvalidException('Game strike window is not open');
        }

        $candidate = $candidates[$key];
        $actor = $candidate['actor'] ?? null;
        $choice = $candidate['choice'] ?? null;
        $attackerRoll = $candidate['attackerRoll'] ?? null;
        if (
            !is_array($actor)
            || !is_string($actor['type'] ?? null)
            || !is_int($actor['id'] ?? null)
            || !is_array($choice)
            || !is_array($attackerRoll)
        ) {
            throw new GameInvalidException('Game strike window choice is invalid');
        }

        $row['target_type'] = $actor['type'];
        $row['target_id'] = $actor['id'];
        $row['reaction'] = $choice['reaction'] ?? null;
        $row['block_item_inventory_id'] = $choice['blockItemInventoryId'] ?? null;
        $row['block_item_profile_index'] = $choice['blockItemProfileIndex'] ?? null;
        $row['block_item_rule_code'] = $choice['blockItemRuleCode'] ?? null;
        $row['expected_sheet_version'] = $candidate['expectedSheetVersion'] ?? 0;

        $primaryKey = is_string($resolution['primaryKey'] ?? null) ? $resolution['primaryKey'] : null;
        $cover = $primaryKey === null
            ? null
            : $this->coverInfoOf($candidates, $primaryKey, $key);

        return [
            'row' => $row,
            'choice' => [
                'reaction' => $choice['reaction'],
                'blockItemInventoryId' => $choice['blockItemInventoryId'] ?? null,
                'blockItemProfileIndex' => $choice['blockItemProfileIndex'] ?? null,
                'blockItemRuleCode' => $choice['blockItemRuleCode'] ?? null,
                'turn' => false,
            ],
            'attackerRoll' => $attackerRoll,
            'defenderRoll' => is_array($candidate['defenderRoll'] ?? null) ? $candidate['defenderRoll'] : null,
            'expectedSheetVersion' => is_int($candidate['expectedSheetVersion'] ?? null)
                ? $candidate['expectedSheetVersion']
                : 0,
            'prepaid' => true,
            'cover' => $cover,
        ];
    }
}
