<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Снятие долга текущего участника.
 */
final class GameTurnAbort
{
    /**
     * Создаёт сценарий.
     *
     * @param GameBattleRepository $battles Строки боя.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
    ) {
    }

    /**
     * Убирает слот долга текущего.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function abort(GameBattleRecord $battle, int $expectedBattle, array $current): array
    {
        $debtMap = $battle->getActionDebt();
        $key = $current['type'] . ':' . $current['id'];
        unset($debtMap[$key]);
        $saved = $this->battles->replaceTracked($battle->getId(), $expectedBattle, null, $debtMap, null, null);

        return ['battleId' => $battle->getId(), 'version' => $saved->getStateVersion(), 'aborted' => true];
    }
}
