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
     * @param list<array<string, mixed>> $targets Строки целей.
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

        $strikeId = $this->actions->addWithTargets($action, $targets);
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
        $snapshot = $this->snapshot($game, $choice);
        $row = $this->action($battle, $sessionId, $choice, $kind);
        $opened = $this->open(
            $game,
            $choice,
            $battle,
            $expectedBattleVersion,
            $snapshot,
            $row['action'],
            $row['targets'],
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
        $strikeId = $this->actions->addWithTargets($row['action'], $row['targets']);

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $saved->getStateVersion(),
        ];
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
     * @return array{action: array<string, mixed>, targets: list<array<string, mixed>>} Строки.
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
            'item_inventory_id' => $choice['itemInventoryId'],
            'item_rule_code' => $choice['itemRuleCode'],
            'profile_type' => $choice['profileType'],
            'profile_index' => $choice['profileIndex'],
            'open' => true,
        ];

        $targets = $kind === 'strike'
            ? [$choice['defender']]
            : $choice['targets'];

        return [
            'action' => $action,
            'targets' => $this->targets($targets),
        ];
    }

    /**
     * Строки целей.
     *
     * @param list<array{type: string, id: int}> $targets Цели.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function targets(array $targets): array
    {
        $rows = [];
        foreach ($targets as $target) {
            $rows[] = [
                'target_type' => $target['type'],
                'target_id' => $target['id'],
                'reaction' => null,
                'block_item_inventory_id' => null,
                'block_item_profile_index' => null,
                'block_item_rule_code' => null,
            ];
        }

        return $rows;
    }
}
