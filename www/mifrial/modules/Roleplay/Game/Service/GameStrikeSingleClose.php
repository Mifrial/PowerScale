<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
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
     * @param GameStrikeTargetClose $targets Расчёт цели.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeSingleChat $chat Чат.
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
        private readonly GameStrikeTargetClose $targets,
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameStrikeSingleChat $chat,
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
    ): array {
        $open = $this->rows->openSingle($battleId, $game, $actorUserId, $editAll);
        $battle = $this->rows->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
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

        $lastStrike = new GameLastStrikeSnapshot();
        $this->choices->assertReaction($game, $target, $requestChoice);
        $choice = $this->authoritativeChoice($game, $target, $requestChoice);
        $turn = ($choice['turn'] ?? false) === true;
        $conditions = is_array($open['conditions'] ?? null)
            ? $open['conditions']
            : (new GameStrikeConditions())->defaults();
        if ($turn && (($conditions['flank'] ?? false) !== true || $choice['reaction'] === 'ignore')) {
            throw new GameInvalidException('Game strike turn is invalid');
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
        $profileType = is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '';
        $profileIndex = is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0;
        $itemInventoryId = is_int($open['item_inventory_id'] ?? null) ? $open['item_inventory_id'] : 0;
        $itemRuleCode = is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '';
        $modifier = $this->rules->actionCharacteristicModifier(
            $slice,
            $actionRuleCode,
            $profileType,
            1,
            $this->rules->attackFilterContext(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $this->choices->attackerChoices($game, $open),
                $itemInventoryId,
            ),
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
        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
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

        $previousSr = $lastStrike->previousSr($pending, $attackerKind, $attackerId, $targetKey);
        $advantages = [];
        $flank = ($conditions['flank'] ?? false) === true && !$turn
            ? new RollAdvantage('flank', -2)
            : null;
        if ($packets > 1) {
            $advantages[] = new RollAdvantage('multi_attack', -$packets);
        } elseif ($sequential) {
            $advantages[] = new RollAdvantage('multi_attack', -1);
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

        $attackerRoll = $this->resolution->rollOf($game, $open, $hitCode, $pending, $slice, $advantages, [$targetKey]);
        $defenderRoll = null;
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

        if ($choice['reaction'] === 'dodge') {
            $shotDistance = $profileType === 'shoot' ? $metrics['distance'] : null;
            $defenderRoll = $this->resolution->dodgeRollOf(
                $game,
                $targetOpen,
                $hitCode,
                $attackerRoll,
                $defenderPending,
                $reactionRuleCode,
                $slice,
                $flank === null ? [] : [$flank],
                $shotDistance,
            );
            $attackerRoll = $profileType === 'shoot' || $profileType === 'throw'
                ? $this->resolution->rerateRangedHit($game, $open, $conditions, $attackerRoll, $defenderRoll, 'dodge')
                : $this->rules->rerateHit($attackerRoll, $defenderRoll);
        }

        if ($choice['reaction'] === 'block') {
            $defenderRoll = $this->resolution->blockRollOf(
                $game,
                $targetOpen,
                $choice,
                $hitCode,
                $attackerRoll,
                $defenderPending,
                $reactionRuleCode,
                $slice,
                $flank === null ? [] : [$flank],
            );
            if ($profileType === 'shoot' || $profileType === 'throw') {
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

        if ($choice['reaction'] === 'ignore' && ($profileType === 'shoot' || $profileType === 'throw')) {
            $attackerRoll = $this->resolution->rerateRangedHit(
                $game,
                $open,
                $conditions,
                $attackerRoll,
                null,
                'ignore',
            );
        }

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
        $success = $autoFail
            ? 0
            : $lastStrike->boostSuccess(
                $slice,
                $actionRuleCode,
                $profileType === 'shoot' || $profileType === 'throw'
                    ? $this->resolution->successOfRanged($attackerRoll)
                    : $this->resolution->successOf($attackerRoll, $defenderRoll, $choice['reaction']),
                $previousSr,
            );
        $damage = $autoFail
            ? $this->amounts->zero()
            : $this->resolution->damageOf($game, $open, $success, 1, $metrics['drop']);
        $result = $this->closeResult(
            $battle,
            $strikeId,
            $expectedBattleVersion,
            $attackerRoll,
            $defenderRoll,
            $success,
            $damage,
        );
        $durabilityShave = $result['success'] === 0
            ? 0
            : $this->rules->durabilityShave(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $actionRuleCode,
                is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '',
                is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '',
                is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0,
                $attackerRoll,
                $result['success'],
            );
        $resistance = $result['success'] === 0
            ? $this->amounts->zero()
            : $this->resolution->resistanceOf(
                $game,
                $targetOpen,
                $this->resolution->resistanceChoice($choice, $result['defenderRoll'] ?? null),
                $result['success'],
                $this->resolution->penetrationOf($game, $open, $success, 1, $metrics['drop']),
                $durabilityShave,
            );
        $soak = $this->resolution->soakOf($game, $targetOpen, $choice['reaction'], $result['success'], $pending);
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
                : $this->resolution->damageOf($game, $open, $success, $packet, $metrics['drop']);
            $packetDamages[] = $packetDamage;
            $packetInjuries[] = $this->amounts->injury(
                $result['success'] === 0 ? $this->amounts->zero() : $packetDamage,
                $resistance,
                $result['success'],
                $soak,
            );
        }

        $injury = $packetInjuries[0];
        $result['damage'] = $this->amounts->view($packetDamages[0]);
        $result['injury'] = $this->amounts->view($injury);
        $result['packetDamages'] = array_map($this->amounts->view(...), $packetDamages);
        $result['packetInjuries'] = array_map($this->amounts->view(...), $packetInjuries);
        $prepared = $autoFail ? null : $this->resolution->prepareSheet(
            $game,
            $targetOpen,
            $this->amounts->sum($packetInjuries),
            $result['success'],
        );
        if (is_array($prepared)) {
            $result = array_merge($result, $prepared['consequence']);
        }

        $result['sheetVersion'] = $autoFail && $spends === []
            ? null
            : ($autoFail
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
        );
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
            $this->battles->replacePending($battleId, $expectedBattleVersion, $pending);
            $result['closed'] = false;
            $result['sequential'] = true;
            $result['resolvedIndex'] = $activeIndex;

            return $result;
        }

        if ($sequential) {
            $this->actions->close($strikeId);
        }

        $pending = (new GamePendingActionCosts())->complete(
            $slice,
            $this->choices->attackerChoices($game, $open),
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $pending,
            ['action-points' => $this->spend->getActionAp($slice, $actionRuleCode, true)],
            [$targetKey],
        );
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

        $this->battles->replacePending($battleId, $expectedBattleVersion, $pending);
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
        $sheet = $kind === 'character'
            ? $this->rules->characterSnapshot($actorId)['sheet']
            : $this->sheets->getNpcSheet($game->getId(), $actorId);
        $levels = $sheet['abilityLevels'] ?? null;

        return is_array($levels) ? $levels : [];
    }
}
