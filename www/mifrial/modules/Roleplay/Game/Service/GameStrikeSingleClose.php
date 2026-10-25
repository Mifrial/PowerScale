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
        $row = is_array($strikes[0] ?? null) ? $strikes[0] : null;
        if ($row === null) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $target = $this->rows->defender($row);
        $targetKey = $this->rows->targetKey($target);
        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $lastStrike = new GameLastStrikeSnapshot();
        $this->choices->assertReaction($game, $target, $requestChoice);
        $choice = $this->authoritativeChoice($game, $target, $requestChoice);
        $targetOpen = $this->rows->targetOpen($open, $target);
        $this->sheets->assertDefenderSheet(
            $game,
            $targetOpen,
            $expectedSheetVersion,
            $actorUserId,
            $viewAll,
        );

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $pending = $battle->getPendingActionEffects();
        $defenderPending = $pending;
        $reactionRuleCode = $choice['reaction'] === 'ignore'
            ? null
            : $this->rules->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $choice['reaction']);
        $defenderChoices = [];
        if ($reactionRuleCode !== null || $choice['reaction'] === 'block') {
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

        $previousSr = $lastStrike->previousSr($pending, $attackerKind, $attackerId, $targetKey);
        $advantages = [];
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
            $defenderRoll = $this->resolution->dodgeRollOf(
                $game,
                $targetOpen,
                $hitCode,
                $attackerRoll,
                $defenderPending,
                $reactionRuleCode,
                $slice,
            );
            $attackerRoll = $this->rules->rerateHit($attackerRoll, $defenderRoll);
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
                $this->resolution->successOf($attackerRoll, $defenderRoll, $choice['reaction']),
                $previousSr,
            );
        $damage = $autoFail ? $this->amounts->zero() : $this->resolution->damageOf($game, $open, $success);
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
                $this->resolution->penetrationOf($game, $open),
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

        $effectiveDamage = $result['success'] === 0 ? $this->amounts->zero() : $damage;
        $injury = $this->amounts->injury($effectiveDamage, $resistance, $result['success'], $soak);
        $result['injury'] = $this->amounts->view($injury);
        $prepared = $autoFail ? null : $this->resolution->prepareSheet(
            $game,
            $targetOpen,
            $injury,
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
        $this->chat->post(
            $game,
            $battle,
            $targetOpen,
            $actorUserId,
            $strikeId,
            $choice['reaction'],
            $result,
        );
        $this->actions->closeWithAnswer($strikeId, [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'],
            'block_item_profile_index' => $choice['blockItemProfileIndex'],
            'block_item_rule_code' => $choice['blockItemRuleCode'],
        ]);

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

        return $result;
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
}
