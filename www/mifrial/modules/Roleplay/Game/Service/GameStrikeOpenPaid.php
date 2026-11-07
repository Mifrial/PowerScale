<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeOpenRun;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Запись удара, когда F1 уже уплачены.
 */
final class GameStrikeOpenPaid
{
    /**
     * Создаёт запись оплаченного удара.
     *
     * @param GameStrikeOpenStore $store Запись боя и действия.
     * @param GameStrikeSpend $spend Списание атакующего.
     * @param GameStrikeOpenLedger $ledger Запись открытия.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeOpenRows $rows Строки удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeOpenStore $store,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeOpenLedger $ledger,
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeOpenRows $rows,
    ) {
    }

    /**
     * Пишет удар, когда F1 уже уплачены.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     *
     * @return array{strikeId: int, version: int} Итог.
     *
     * @throws GameInvalidException Если вид, строка или ресурсы невалидны.
     * @throws GameNotFoundException Если ревизии или NPC нет.
     * @throws GameBattleConflictException Если CAS атакующего устарел.
     */
    public function paid(GameStrikeOpenRun $run): array
    {
        $this->inventory($run);
        $debt = $this->spend->releaseDebt($run->battle, $run->attackerKind, $run->attackerId);
        $saved = $this->store->replaceDebt(
            $run->battle->getId(),
            $run->expectedBattleVersion,
            $run->battle->getPendingActionEffects(),
            $debt,
        );
        $run->choice['tokenSpendInfo'] = $this->tokenInfo($run);
        $row = $this->rows->action($run->battle, $run->sessionId, $run->choice, $run->kind);
        $prepared = $this->ledger->attachProcess($run, $row['action'], $row['strikes'], $run->context, null);
        $strikeId = $this->store->addStrikes($prepared['action'], $prepared['strikes']);

        return ['strikeId' => $strikeId, 'version' => $saved->getStateVersion()];
    }

    /**
     * Списывает предмет или боеприпас открытого объявления.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     *
     * @return void
     *
     * @throws GameInvalidException Если профиль или предмет невалидны.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    private function inventory(GameStrikeOpenRun $run): void
    {
        $game = $run->game;
        $choice = $run->choice;
        $snapshot = $run->snapshot;
        $inventory = $this->rules->cards->pushOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
        ) !== null ? null : $this->rules->spends->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $run->attackerKind,
            $run->attackerId,
            $run->attackerKind === 'npc' ? $snapshot['sheet']['choices'] : null,
        );
        if ($inventory === null) {
            return;
        }

        $this->store->writeInventory($game, $run->attackerKind, $run->attackerId, $snapshot['actualVersion'], $inventory);
    }

    /**
     * Данные жетона для строки удара.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     *
     * @return array<string, mixed> Данные.
     */
    private function tokenInfo(GameStrikeOpenRun $run): array
    {
        $raw = $run->choice['tokenSpend'] ?? null;
        if ($raw === null) {
            return [];
        }

        $amount = is_array($raw) ? ($raw['amount'] ?? 0) : $raw;
        if (!is_int($amount) || $amount < 1) {
            return [];
        }

        $sheet = is_array($run->snapshot['sheet']['sheet'] ?? null) ? $run->snapshot['sheet']['sheet'] : [];
        $slice = $this->rules->data->ruleSlice($run->game->getSpaceId(), $run->game->getRulesRevision());
        $resource = (new GameConcentration())->resourceCode($slice, $sheet) ?? '';

        return [
            'resource' => $resource,
            'amount' => $amount,
            'source' => is_array($raw) && is_string($raw['source'] ?? null) ? $raw['source'] : $resource,
            'mode' => is_array($raw) && is_string($raw['mode'] ?? null) ? $raw['mode'] : 'advantage',
        ];
    }
}
