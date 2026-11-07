<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Запись боя, действия и листа открытого удара.
 */
final class GameStrikeOpenStore
{
    /**
     * Создаёт запись открытия.
     *
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeSheetWrites $sheetWrites,
    ) {
    }

    /**
     * Пишет ожидание, долг и цикл боя.
     *
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $pending Ожидание.
     * @param array<string, mixed> $debt Долг.
     * @param array<string, mixed>|null $cycle Цикл.
     *
     * @return GameBattleRecord Строка после записи.
     *
     * @throws GameBattleConflictException Если версия боя другая.
     * @throws GameInvalidException Если поле боя невалидно.
     */
    public function replaceDebt(
        int $battleId,
        int $expectedBattleVersion,
        array $pending,
        array $debt,
        ?array $cycle = null,
    ): GameBattleRecord {
        return $this->battles->replaceTracked(
            $battleId,
            $expectedBattleVersion,
            $pending,
            $debt,
            null,
            null,
            $cycle,
        );
    }

    /**
     * Пишет ожидание боя.
     *
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия боя другая.
     * @throws GameInvalidException Если поле боя невалидно.
     */
    public function replacePending(int $battleId, int $expectedBattleVersion, array $pending): void
    {
        $this->battles->replacePending($battleId, $expectedBattleVersion, $pending);
    }

    /**
     * Пишет строку действия и удары.
     *
     * @param array<string, mixed> $action Строка действия.
     * @param list<array<string, mixed>> $targets Строки ударов.
     *
     * @return int Id удара.
     *
     * @throws GameInvalidException Если строка действия невалидна.
     */
    public function addStrikes(array $action, array $targets): int
    {
        return $this->actions->addWithStrikes($action, $targets);
    }

    /**
     * Списывает строку инвентаря в лист.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид атакующего.
     * @param int $id Атакующий.
     * @param int $version Версия листа.
     * @param array{kind: string, inventoryId: int, ruleCode: string} $inventory Списание.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если операция отвергнута.
     * @throws GameNotFoundException Если строки или ревизии нет.
     */
    public function writeInventory(GameRecord $game, string $kind, int $id, int $version, array $inventory): void
    {
        $this->sheetWrites->write($game, $kind, $id, $version, [], null, null, [], $inventory);
    }
}
