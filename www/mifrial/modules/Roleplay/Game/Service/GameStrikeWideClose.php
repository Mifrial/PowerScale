<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Закрытие широкого удара.
 */
final class GameStrikeWideClose
{
    /**
     * Создаёт закрытие.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeAccess $access Права ответа.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeTargetClose $targets Расчёт цели.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeWideChat $chat Чат.
     * @param GameStrikeSpend $spend Стоимость.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeResolution $resolution Расчёт.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeTargetClose $targets,
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameStrikeWideChat $chat,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameStrikeResolution $resolution,
    ) {
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
    public function close(
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

        $targetKeys = array_keys($indexByTarget);

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
        $attackerRoll = $this->targets->rollOf($game, $open, $battle, $slice, count($rows), $targetKeys);
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

        $this->targets->begin($battle->getPendingActionEffects());
        $results = [];
        foreach ($rows as $index => $row) {
            $results[] = $this->targets->one(
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

        $this->chat->post(
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
            $this->choices->attackerChoices($game, $open),
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $this->targets->pendingAfter(),
            ['action-points' => $this->spend->getActionAp($slice, $actionRuleCode)],
            $targetKeys,
        );

        $hits = [];
        foreach ($results as $index => $record) {
            $target = is_array($record['target'] ?? null) ? $record['target'] : null;
            if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
                continue;
            }

            $injury = is_array($record['injury'] ?? null) ? $record['injury'] : null;
            $hits[] = [
                'targetKey' => $target['type'] . ':' . $target['id'],
                'attackSr' => is_int($record['success'] ?? null) ? $record['success'] : 0,
                'reaction' => is_string($rows[$index]['reaction'] ?? null) ? $rows[$index]['reaction'] : 'ignore',
                'damaged' => $injury !== null && (new DimensionalNumber(
                    (int) ($injury['base'] ?? 0),
                    (int) ($injury['size'] ?? 0),
                ))->toInteger() > 0,
            ];
        }

        $lastStrike = new GameLastStrikeSnapshot();
        $pending = $lastStrike->store(
            $pending,
            $attackerKind,
            $attackerId,
            $actionRuleCode,
            $lastStrike->kindOf($slice, $actionRuleCode),
            $hits,
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
                $this->choices->targetChoices($game, $target),
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
            $this->choices->assertReaction($game, $target, $choice);
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
}
