<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Сборка записи открытого удара.
 */
final class GameStrikeOpenFactory
{
    /**
     * Создаёт запись открытия.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия.
     * @param GameStrikeSpend $spend Списание.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return GameStrikeOpen Запись открытия.
     */
    public function create(
        GameStrikeRules $rules,
        GameBattleRepository $battles,
        GameActionRepository $actions,
        GameStrikeSpend $spend,
        GameStrikeSheets $sheets,
        GameStrikeSheetWrites $sheetWrites,
        GameProcessFlow $processes,
    ): GameStrikeOpen {
        $process = new GameStrikeOpenProcess($processes);
        $store = new GameStrikeOpenStore($battles, $actions, $sheetWrites);
        $rows = new GameStrikeOpenRows();
        $ledger = new GameStrikeOpenLedger($store, $spend, $process, new GameStrikeOpenTargets($rules));

        return new GameStrikeOpen(
            $process,
            new GameStrikeOpenView($rules, $sheets),
            $rows,
            $ledger,
            new GameStrikeOpenPaid($store, $spend, $ledger, $rules, $rows),
        );
    }
}
