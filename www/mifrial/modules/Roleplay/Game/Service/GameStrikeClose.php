<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Закрытие удара по одной цели или по списку целей.
 */
final class GameStrikeClose
{
    /**
     * Карта ожидания после последнего расчёта.
     *
     * @var array<string, mixed>
     */
    private array $pendingAfter = [];

    private readonly GameStrikeSingleChat $singleChat;

    private readonly GameStrikeWideChat $wideChat;

    private readonly GameStrikeCloseRows $rows;

    /**
     * Создаёт закрытие удара.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameStrikeAccess $access Права ответа.
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Расчёт бросков и урона.
     * @param GameStrikeSpend $spend Траты реакции.
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Чат последствий.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameReplayTransaction $replayTransaction Граница replay.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameCommandRepository $commands,
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameReplayTransaction $replayTransaction,
    ) {
        $this->singleChat = new GameStrikeSingleChat(
            $this->chat,
            $this->consequences,
            $this->spend,
            $this->rules,
        );
        $this->wideChat = new GameStrikeWideChat(
            $this->chat,
            $this->consequences,
            $this->spend,
            $this->rules,
        );
        $this->rows = new GameStrikeCloseRows($this->battles, $this->actions, $this->access);
    }

    /**
     * Принимает ответы целей широкого удара и закрывает его при полноте.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array<string, mixed>> $choices Ответы целей.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    public function closeWide(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choices,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $body = [
            'battleId' => $battle->getId(),
            'defense' => $choices,
            'expectedVersion' => $expectedBattleVersion,
        ];

        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'wide_strike', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'wide_strike',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->closeWideWork(
                $game,
                $battle,
                $expectedBattleVersion,
                $choices,
                $actorUserId,
                $editAll,
                $viewAll,
            ),
            $body,
        )['result'];
    }

    /**
     * Закрывает одиночный удар.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    public function closeSingle(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $choice,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $body = [
            'battleId' => $battleId,
            'defense' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
            'expectedVersion' => $expectedBattleVersion,
        ];

        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'strike', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'strike',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->closeSingleWork(
                $game,
                $sessionId,
                $battleId,
                $expectedBattleVersion,
                $expectedSheetVersion,
                $choice,
                $actorUserId,
                $editAll,
                $viewAll,
            ),
            $body,
        )['result'];
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
    private function closeSingleWork(
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

        $targets = is_array($open['targets'] ?? null) ? $open['targets'] : [];
        $row = is_array($targets[0] ?? null) ? $targets[0] : null;
        if ($row === null) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $target = $this->rows->defender($row);
        $this->assertReaction($game, $target, $requestChoice);
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
            $defenderChoices = $this->targetChoices($game, $target);
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

        $attackerRoll = $this->resolution->rollOf($game, $open, $hitCode, $pending, $slice);
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
        $damage = $autoFail ? $this->amounts->zero() : $this->resolution->damageOf($game, $open);
        $result = $this->closeResult(
            $battle,
            $strikeId,
            $expectedBattleVersion,
            $attackerRoll,
            $defenderRoll,
            $choice['reaction'],
            $autoFail ? $this->amounts->zero() : $damage,
        );
        $resistance = $result['success'] === 0
            ? $this->amounts->zero()
            : $this->resolution->resistanceOf(
                $game,
                $targetOpen,
                $this->resolution->resistanceChoice($choice, $result['defenderRoll'] ?? null),
                $result['success'],
                $this->resolution->penetrationOf($game, $open),
            );
        $soak = $choice['reaction'] === 'dodge' ? $this->resolution->soakOf($game, $targetOpen) : null;
        $result['resistance'] = $this->amounts->view($resistance);
        if ($soak !== null) {
            $result['S'] = $this->amounts->view($soak);
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
        $this->singleChat->post(
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

        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $pending = (new GamePendingActionCosts())->complete(
            $slice,
            $this->attackerChoices($game, $open),
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $pending,
            ['action-points' => $this->spend->getActionAp($slice, $actionRuleCode)],
        );
        if ($reactionRuleCode !== null) {
            $pending = (new GamePendingActionCosts())->complete(
                $slice,
                $defenderChoices,
                $reactionRuleCode,
                $target['type'],
                $target['id'],
                $defenderPending,
                $this->spend->getSpendAmounts($spends),
            );
        }

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
            $this->targetChoices($game, $target),
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
     * @param string $reaction Реакция.
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
        string $reaction,
        DimensionalNumber $damage,
    ): array {
        $success = $this->resolution->successOf($attackerRoll, $defenderRoll, $reaction);
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
     * Тело закрытия широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array<string, mixed>> $requestChoices Ответы запроса.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    private function closeWideWork(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $requestChoices,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $battleId = $battle->getId();
        $open = $this->rows->requireOpen($battleId, 'wide_strike');
        $strikeId = $open['id'];
        $rows = $this->actions->getTargets($strikeId);
        $indexByTarget = [];
        foreach ($rows as $index => $row) {
            $indexByTarget[$this->rows->targetKey($this->rows->rowTarget($row))] = $index;
        }

        foreach ($requestChoices as $choice) {
            $index = $indexByTarget[$this->rows->targetKey($choice['target'])] ?? null;
            if (!is_int($index)) {
                throw new GameInvalidException('Game wide strike target is invalid');
            }

            $this->access->assertTarget($game, $actorUserId, $editAll, $choice['target']);
            $this->assertTargetSheet(
                $game,
                $battleId,
                $choice['target'],
                $choice,
                $actorUserId,
                $viewAll,
            );
            $targetId = $rows[$index]['id'] ?? null;
            if (!is_int($targetId)) {
                throw new GameInvalidException('Game wide strike target row is invalid');
            }

            $this->actions->answerTarget($targetId, [
                'reaction' => $choice['reaction'],
                'block_item_inventory_id' => $choice['blockItemInventoryId'],
                'block_item_profile_index' => $choice['blockItemProfileIndex'],
                'block_item_rule_code' => $choice['blockItemRuleCode'],
                'expected_sheet_version' => $choice['expectedSheetVersion'],
            ]);
            $rows[$index]['reaction'] = $choice['reaction'];
            $rows[$index]['block_item_inventory_id'] = $choice['blockItemInventoryId'];
            $rows[$index]['block_item_profile_index'] = $choice['blockItemProfileIndex'];
            $rows[$index]['block_item_rule_code'] = $choice['blockItemRuleCode'];
            $rows[$index]['expected_sheet_version'] = $choice['expectedSheetVersion'];
        }

        $pendingTargets = $this->rows->pendingTargets($rows);
        if ($pendingTargets !== []) {
            return [
                'battleId' => $battleId,
                'strikeId' => $strikeId,
                'version' => $expectedBattleVersion,
                'closed' => false,
                'pendingTargets' => $pendingTargets,
                'targetResults' => [],
            ];
        }

        $choices = [];
        foreach ($rows as $row) {
            $choices[] = $this->rows->answerOfRow($row);
        }

        $refusals = $this->assertSheets(
            $game,
            $battleId,
            $rows,
            $choices,
            $actorUserId,
            $viewAll,
        );
        $choices = $this->resolveChoices($game, $rows, $choices, $refusals);

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $attackerRoll = $this->rollOf($game, $open, $battle, $slice, count($rows));
        $damage = $this->rules->isHitAutoFail($attackerRoll['roll'])
            ? $this->amounts->zero()
            : $this->resolution->damageOf($game, $open);

        $penetration = null;
        $penetrationFactory = function () use (&$penetration, $game, $open): DimensionalNumber {
            if (!$penetration instanceof DimensionalNumber) {
                $penetration = $this->resolution->penetrationOf($game, $open);
            }

            return $penetration;
        };

        $this->pendingAfter = $battle->getPendingActionEffects();
        $results = [];
        foreach ($rows as $index => $row) {
            $results[] = $this->one(
                $game,
                $open,
                $row,
                $choices[$index],
                $refusals[$index] ?? null,
                $damage,
                $attackerRoll,
                $hitCode,
                $penetrationFactory,
                $actorUserId,
                $viewAll,
            );
        }

        $this->wideChat->post(
            $game,
            $rows,
            $choices,
            $results,
            $attackerRoll,
            $open,
            $battle,
            $slice,
            $actorUserId,
        );
        $this->storeReactions($rows, $choices, $results);

        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game wide strike attacker is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $pending = (new GamePendingActionCosts())->complete(
            $slice,
            $this->attackerChoices($game, $open),
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $this->pendingAfter,
            ['action-points' => $this->spend->getActionAp($slice, $actionRuleCode)],
        );

        $this->actions->close($strikeId);
        $this->battles->replacePending($battleId, $expectedBattleVersion, $pending);

        return [
            'battleId' => $battleId,
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion + 1,
            'attackerRoll' => $attackerRoll,
            'targetResults' => $results,
            'closed' => true,
            'pendingTargets' => [],
        ];
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
    private function one(
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
        $target = $this->rows->defender($row);
        if ($refusalCode !== null) {
            return $this->row($target, $refusalCode, null, null, null);
        }

        $reaction = $this->reactionOf($game, $target, $choice);
        if ($reaction['insufficient']) {
            $zero = $this->amounts->view($this->amounts->zero());

            return $this->row($target, null, null, $zero, $zero, null, $zero);
        }

        $spends = $reaction['spends'];
        $slice = $reaction['slice'];
        $reactionRuleCode = $reaction['reactionRuleCode'];
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
        $outcome = $this->damageOf(
            $game,
            $open,
            $target,
            $choice,
            $defense['defenderRoll'],
            $defense['success'],
            $damage,
            $penetrationFactory,
        );
        $record = $this->row(
            $target,
            null,
            $defense['success'],
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
                    $defense['success'],
                    $spends,
                    $actorUserId,
                    $viewAll,
                    $record,
                );
        }

        if ($reactionRuleCode !== null && $slice !== null) {
            $this->pendingAfter = (new GamePendingActionCosts())->complete(
                $slice,
                $this->targetChoices($game, $target),
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
            $this->targetChoices($game, $target),
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
                    $this->targetChoices($game, $target),
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
        $targetOpen = $this->rows->targetOpen($open, $target);
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
        $targetOpen = $this->rows->targetOpen($open, $target);
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
            $this->rows->targetOpen($open, $target),
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
     *
     * @return array<string, mixed> Roll.
     */
    private function rollOf(
        GameRecord $game,
        array $open,
        GameBattleRecord $battle,
        CharacterRuleSlice $slice,
        int $targetCount,
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
        );
    }

    /**
     * Проверяет листы всех целей и отдаёт non-CAS отказы.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return list<string|null> Отказы.
     */
    private function assertSheets(
        GameRecord $game,
        int $battleId,
        array $rows,
        array $choices,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $refusals = [];
        foreach ($rows as $index => $row) {
            $target = $this->rows->defender($row);
            $refusals[$index] = $this->assertTargetSheet(
                $game,
                $battleId,
                $target,
                $choices[$index],
                $actorUserId,
                $viewAll,
            );
        }

        return $refusals;
    }

    /**
     * Проверяет лист одной цели.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return string|null Non-CAS код отказа.
     */
    private function assertTargetSheet(
        GameRecord $game,
        int $battleId,
        array $target,
        array $choice,
        int $actorUserId,
        bool $viewAll,
    ): ?string {
        $code = $this->refusal($game, $battleId, $target, $choice);
        if ($code !== null) {
            return $code;
        }

        $this->sheets->assertTargetSheet(
            $game,
            $target,
            $choice['expectedSheetVersion'],
            $actorUserId,
            $viewAll,
        );

        return null;
    }

    /**
     * Разрешает коды block из authoritative inventory целей.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<string|null> $refusals Отказы.
     *
     * @return list<array<string, mixed>> Authoritative защиты.
     */
    private function resolveChoices(
        GameRecord $game,
        array $rows,
        array $choices,
        array $refusals,
    ): array {
        foreach ($rows as $index => $row) {
            if (($refusals[$index] ?? null) !== null || ($choices[$index]['reaction'] ?? null) !== 'block') {
                continue;
            }

            $target = $this->rows->defender($row);
            $inventoryId = $choices[$index]['blockItemInventoryId'] ?? null;
            if (!is_int($inventoryId)) {
                throw new GameInvalidException('Game wide strike block item is invalid');
            }

            $choices[$index]['blockItemRuleCode'] = $this->rules->getBlockItemRuleCode(
                $this->targetChoices($game, $target),
                $inventoryId,
            );
        }

        return $choices;
    }

    /**
     * Код отказа или null.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return string|null Код.
     */
    private function refusal(GameRecord $game, int $battleId, array $target, array $choice): ?string
    {
        $code = null;
        try {
            $this->rows->assertDefender($battleId, $target);
            $this->assertReaction($game, $target, $choice);
        } catch (GameNotFoundException) {
            $code = 'GAME_NOT_FOUND';
        } catch (GameInvalidException) {
            $code = 'GAME_INVALID';
        }

        return $code;
    }

    /**
     * Пишет реакции принятых целей.
     *
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<array<string, mixed>> $results Итоги.
     *
     * @return void
     */
    private function storeReactions(array $rows, array $choices, array $results): void
    {
        foreach ($results as $index => $result) {
            if (($result['code'] ?? null) !== null) {
                continue;
            }

            $targetId = $rows[$index]['id'] ?? null;
            if (!is_int($targetId)) {
                throw new GameInvalidException('Game wide strike target row is invalid');
            }

            $this->actions->answerTarget($targetId, [
                'reaction' => $choices[$index]['reaction'],
                'block_item_inventory_id' => $choices[$index]['blockItemInventoryId'],
                'block_item_profile_index' => $choices[$index]['blockItemProfileIndex'],
                'block_item_rule_code' => $choices[$index]['blockItemRuleCode'],
            ]);
        }
    }

    /**
     * Авторитетные choices цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Choices.
     */
    private function targetChoices(GameRecord $game, array $target): array
    {
        if ($target['type'] === 'character') {
            return $this->rules->characterSnapshot($target['id'])['choices'];
        }

        return $this->defenderChoices($game->getId(), $target['id']);
    }

    /**
     * Авторитетные choices атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return array<string, mixed> Choices.
     */
    private function attackerChoices(GameRecord $game, array $open): array
    {
        $kind = $open['attacker_kind'] ?? null;
        $id = $open['attacker_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game wide strike attacker is invalid');
        }

        if ($kind === 'character') {
            return $this->rules->characterSnapshot($id)['choices'];
        }

        $snapshot = $this->sheets->getNpcSnapshot($game->getId(), $id);
        $choices = $snapshot['sheet']['choices'] ?? null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are invalid');
        }

        return $choices;
    }

    /**
     * Документ choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ.
     */
    private function defenderChoices(int $gameId, int $npcId): array
    {
        $snapshot = $this->sheets->getNpcSnapshot($gameId, $npcId);
        $choices = $snapshot['sheet']['choices'] ?? null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Реакция и предмет блока этой цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return void
     */
    private function assertReaction(GameRecord $game, array $target, array $choice): void
    {
        if (!in_array($choice['reaction'], ['ignore', 'dodge', 'block'], true)) {
            throw new GameInvalidException('Game wide strike reaction is invalid');
        }

        if (
            $choice['reaction'] === 'block'
            && (!is_int($choice['blockItemInventoryId'])
                || !is_int($choice['blockItemProfileIndex']))
        ) {
            throw new GameInvalidException('Game wide strike block item is invalid');
        }

        if ($choice['reaction'] === 'block') {
            $this->rules->assertBlockSelection(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
                $choice['blockItemProfileIndex'],
                $choice['blockItemRuleCode'],
            );
        }
    }

    /**
     * Запись цели.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param string|null $code Код отказа.
     * @param int|null $rating Успех.
     * @param array{base: int, size: int}|null $damage Урон.
     * @param array{base: int, size: int}|null $resistance Сопротивление.
     * @param array{base: int, size: int}|null $soak Смягчение.
     * @param array{base: int, size: int}|null $injury Повреждение.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     *
     * @return array<string, mixed> Запись.
     */
    private function row(
        array $target,
        ?string $code,
        ?int $rating,
        ?array $damage,
        ?array $resistance,
        ?array $soak = null,
        ?array $injury = null,
        ?array $defenderRoll = null,
    ): array {
        $record = [
            'target' => $target,
            'success' => $rating,
            'damage' => $damage,
            'resistance' => $resistance,
            'injury' => $injury,
            'sheetVersion' => null,
            'code' => $code,
        ];
        if ($defenderRoll !== null) {
            $record['defenderRoll'] = $defenderRoll;
        }

        if ($soak !== null) {
            $record['S'] = $soak;
        }

        return $record;
    }
}
