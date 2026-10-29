<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Запись открытого удара: списание, долг, строка и цели.
 */
final class GameStrikeOpen
{
    /**
     * Создаёт запись открытия.
     *
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeSpend $spend Списание атакующего.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
    ) {
    }

    /**
     * Списывает стоимость и пишет открытый удар или удержанный долг.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $snapshot Снимок атакующего.
     * @param array<string, mixed> $action Строка действия.
     * @param list<array<string, mixed>> $targets Строки ударов.
     * @param string $lane Полоса долга.
     * @param string $subject Подлежащее сообщений об ошибке.
     *
     * @return array{debt: bool, strikeId: int, version: int} Итог.
     */
    public function open(
        GameRecord $game,
        array $choice,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $snapshot,
        array $action,
        array $targets,
        string $lane,
        string $subject,
    ): array {
        $attacker = $choice['attacker'] ?? null;
        $attackerKind = is_array($attacker) ? ($attacker['type'] ?? null) : null;
        $attackerId = is_array($attacker) ? ($attacker['id'] ?? null) : null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $npcSheet = $attackerKind === 'npc' && is_array($snapshot['sheet']['sheet'] ?? null)
            ? $snapshot['sheet']['sheet']
            : null;
        $npcChoices = $attackerKind === 'npc' && is_array($snapshot['sheet']['choices'] ?? null)
            ? $snapshot['sheet']['choices']
            : null;
        $attackerChoices = is_array($snapshot['sheet']['choices'] ?? null) ? $snapshot['sheet']['choices'] : [];
        $attackerSheet = is_array($snapshot['sheet']['sheet'] ?? null) ? $snapshot['sheet']['sheet'] : [];
        $abilityLevels = is_array($attackerSheet['abilityLevels'] ?? null) ? $attackerSheet['abilityLevels'] : [];
        foreach ($targets as $target) {
            $profileType = $target['profile_type'] ?? null;
            $profileIndex = $target['profile_index'] ?? null;
            $itemInventoryId = $target['item_inventory_id'] ?? null;
            $itemRuleCode = $target['item_rule_code'] ?? null;
            if (
                !is_string($profileType)
                || !is_int($profileIndex)
                || !is_int($itemInventoryId)
                || !is_string($itemRuleCode)
            ) {
                throw new GameInvalidException('Game strike row is invalid');
            }

            $this->rules->assertConditions(
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
                is_int($snapshot['actualVersion'] ?? null) ? $snapshot['actualVersion'] : null,
                is_array($target['conditions'] ?? null)
                    ? $target['conditions']
                    : (new GameStrikeConditions())->defaults(),
                $this->rules->actionCharacteristicModifier(
                    $slice,
                    is_string($action['action_rule_code'] ?? null) ? $action['action_rule_code'] : '',
                    $profileType,
                    1,
                    $this->rules->attackFilterContext(
                        $game->getSpaceId(),
                        $game->getRulesRevision(),
                        $itemRuleCode,
                        $profileType,
                        $profileIndex,
                        $attackerChoices,
                        $itemInventoryId,
                    ),
                    $abilityLevels,
                ),
                is_string($action['action_rule_code'] ?? null) ? $action['action_rule_code'] : '',
            );
        }

        $hold = $this->spend->spendAttacker($game, $choice, $battle, $snapshot, $lane, $subject);
        if ($hold['debtHold'] !== null) {
            $saved = $this->battles->replaceTracked(
                $battle->getId(),
                $expectedBattleVersion,
                $hold['pending'],
                $hold['debtHold'],
                null,
                null,
            );

            return ['debt' => true, 'strikeId' => 0, 'version' => $saved->getStateVersion()];
        }

        $strikeId = $this->actions->addWithStrikes($action, $targets);
        $this->battles->replacePending($battle->getId(), $expectedBattleVersion, $hold['pending']);

        return ['debt' => false, 'strikeId' => $strikeId, 'version' => $expectedBattleVersion + 1];
    }

    /**
     * Открывает удар через общий контур.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Выбор.
     * @param string $kind Вид удара.
     * @param string $lane Полоса долга.
     * @param string $subject Подлежащее.
     *
     * @return array<string, mixed> Итог.
     */
    public function openAction(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        string $kind,
        string $lane,
        string $subject,
    ): array {
        $this->assertFollowUp($game, $battle, $choice);
        $snapshot = $this->snapshot($game, $choice);
        $row = $this->action($battle, $sessionId, $choice, $kind);
        $opened = $this->open(
            $game,
            $choice,
            $battle,
            $expectedBattleVersion,
            $snapshot,
            $row['action'],
            $row['strikes'],
            $lane,
            $subject,
        );

        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => $opened['strikeId'],
            'version' => $opened['version'],
        ];
        if ($kind === 'wide_strike') {
            $result['targetResults'] = [];
        } else {
            $result['sheetVersion'] = null;
        }

        if ($opened['debt']) {
            $result['debt'] = true;
        }

        return $result;
    }

    /**
     * Открывает удар, когда F1 уже уплачены.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Выбор.
     * @param string $kind Вид удара.
     *
     * @return array<string, mixed> Итог.
     */
    public function openPaidAction(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        string $kind,
    ): array {
        $this->assertFollowUp($game, $battle, $choice);
        $snapshot = $this->snapshot($game, $choice);
        $attacker = $choice['attacker'];
        $inventorySpend = $this->rules->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $attacker['type'],
            $attacker['id'],
            $attacker['type'] === 'npc' ? $snapshot['sheet']['choices'] : null,
        );
        if ($inventorySpend !== null) {
            $this->sheetWrites->write(
                $game,
                $attacker['type'],
                $attacker['id'],
                $snapshot['actualVersion'],
                [],
                null,
                null,
                [],
                $inventorySpend,
            );
        }

        $debt = $this->spend->releaseDebt($battle, $attacker['type'], $attacker['id']);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            $battle->getPendingActionEffects(),
            $debt,
            null,
            null,
        );

        $row = $this->action($battle, $sessionId, $choice, $kind);
        $strikeId = $this->actions->addWithStrikes($row['action'], $row['strikes']);

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $saved->getStateVersion(),
        ];
    }

    /**
     * Гейт добивания по снимку прошлого удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return void
     *
     * @throws GameInvalidException Если добивание не разрешено.
     */
    private function assertFollowUp(GameRecord $game, GameBattleRecord $battle, array $choice): void
    {
        $attacker = $choice['attacker'] ?? null;
        $actionRuleCode = $choice['actionRuleCode'] ?? null;
        if (
            !is_array($attacker)
            || !is_string($attacker['type'] ?? null)
            || !is_int($attacker['id'] ?? null)
            || !is_string($actionRuleCode)
        ) {
            return;
        }

        (new GameLastStrikeSnapshot())->assertFollowUp(
            $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            $actionRuleCode,
            $battle->getPendingActionEffects(),
            $attacker['type'],
            $attacker['id'],
            $this->targetKeys($choice),
        );
    }

    /**
     * Ключи целей выбора.
     *
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<int, string> Ключи.
     */
    private function targetKeys(array $choice): array
    {
        $targets = is_array($choice['targets'] ?? null) ? $choice['targets'] : [$choice['defender'] ?? null];
        $keys = [];
        foreach ($targets as $target) {
            if (is_array($target) && is_string($target['type'] ?? null) && is_int($target['id'] ?? null)) {
                $keys[] = $target['type'] . ':' . $target['id'];
            }
        }

        return $keys;
    }

    /**
     * Снимок атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Снимок.
     *
     * @throws GameInvalidException Если вид атакующего битый.
     */
    private function snapshot(GameRecord $game, array $choice): array
    {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return $kind === 'character'
            ? $this->sheets->getCharacterSnapshot($id)
            : $this->sheets->getNpcSnapshot($game->getId(), $id);
    }

    /**
     * Строка действия и цели.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $sessionId Сессия.
     * @param array<string, mixed> $choice Выбор.
     * @param string $kind Вид удара.
     *
     * @return array{action: array<string, mixed>, strikes: list<array<string, mixed>>} Строки.
     *
     * @throws GameInvalidException Если вид или профиль битый.
     */
    private function action(
        GameBattleRecord $battle,
        int $sessionId,
        array $choice,
        string $kind,
    ): array {
        if ($kind !== 'strike' && $kind !== 'wide_strike') {
            throw new GameInvalidException('Game strike kind is invalid');
        }

        if ($kind === 'strike' && !in_array($choice['profileType'], ['strike', 'throw', 'shoot'], true)) {
            throw new GameInvalidException('Game strike profile is invalid');
        }

        $action = [
            'kind' => $kind,
            'battle_id' => $battle->getId(),
            'session_id' => $sessionId,
            'attacker_kind' => $choice['attacker']['type'],
            'attacker_id' => $choice['attacker']['id'],
            'action_rule_code' => $choice['actionRuleCode'],
            'open' => true,
        ];

        $targets = $kind === 'strike'
            ? [$choice['defender']]
            : $choice['targets'];

        $strikes = is_array($choice['strikes'] ?? null) && $choice['strikes'] !== []
            ? $this->strikeRows($choice['strikes'])
            : $this->strikes($targets, $choice);

        return [
            'action' => $action,
            'strikes' => $strikes,
        ];
    }

    /**
     * Строки ударов из разобранного выбора.
     *
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function strikeRows(array $strikes): array
    {
        $rows = [];
        foreach ($strikes as $strike) {
            $rows[] = [
                'instance_index' => $strike['instanceIndex'],
                'item_inventory_id' => $strike['itemInventoryId'],
                'item_rule_code' => $strike['itemRuleCode'],
                'profile_type' => $strike['profileType'],
                'profile_index' => $strike['profileIndex'],
                'target_type' => $strike['target']['type'],
                'target_id' => $strike['target']['id'],
                'conditions' => $strike['conditions'] ?? (new GameStrikeConditions())->defaults(),
                'reaction' => null,
                'block_item_inventory_id' => null,
                'block_item_profile_index' => null,
                'block_item_rule_code' => null,
            ];
        }

        return $rows;
    }

    /**
     * Строки ударов.
     *
     * @param list<array{type: string, id: int}> $targets Цели.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function strikes(array $targets, array $choice): array
    {
        $instanceIndex = is_int($choice['instanceIndex'] ?? null) ? $choice['instanceIndex'] : 0;
        $rows = [];
        foreach ($targets as $target) {
            $key = $target['type'] . ':' . $target['id'];
            $conditions = $choice['conditions'][$key] ?? $choice['conditions'] ?? null;
            $rows[] = [
                'instance_index' => $instanceIndex,
                'item_inventory_id' => $choice['itemInventoryId'],
                'item_rule_code' => $choice['itemRuleCode'],
                'profile_type' => $choice['profileType'],
                'profile_index' => $choice['profileIndex'],
                'target_type' => $target['type'],
                'target_id' => $target['id'],
                'conditions' => is_array($conditions)
                    ? $conditions
                    : (new GameStrikeConditions())->defaults(),
                'after_strike_options' => is_array($choice['afterStrikeOptions'] ?? null)
                    ? array_values($choice['afterStrikeOptions'])
                    : [],
                'reaction' => null,
                'block_item_inventory_id' => null,
                'block_item_profile_index' => null,
                'block_item_rule_code' => null,
            ];
        }

        return $rows;
    }
}
