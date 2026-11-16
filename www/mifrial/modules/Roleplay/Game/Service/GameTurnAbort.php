<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
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
     * @param GameSpellCastChat $chat Строки сотворения в долг.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameSpellCastChat $chat,
    ) {
    }

    /**
     * Убирает слот долга текущего.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
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
    public function abort(
        GameRecord $game,
        int $actorUserId,
        GameBattleRecord $battle,
        int $expectedBattle,
        array $current,
    ): array {
        $debtMap = $battle->getActionDebt();
        $key = $current['type'] . ':' . $current['id'];
        $slot = $debtMap[$key] ?? null;
        unset($debtMap[$key]);
        $saved = $this->battles->replaceTracked($battle->getId(), $expectedBattle, null, $debtMap, null, null);
        $this->notify($game, $actorUserId, $battle, $current, $slot);

        return ['battleId' => $battle->getId(), 'version' => $saved->getStateVersion(), 'aborted' => true];
    }

    /**
     * Пишет отказ от сотворения в долг.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $current Текущий.
     * @param mixed $slot Слот до снятия.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    private function notify(GameRecord $game, int $actorUserId, GameBattleRecord $battle, array $current, mixed $slot): void
    {
        if (!is_array($slot)) {
            return;
        }

        $debt = new GameActionDebt();
        if ($debt->kindOf($slot) !== 'spell') {
            return;
        }

        $payload = $debt->payloadOf($slot);
        $code = $payload['spellCode'] ?? null;
        if (!is_string($code) || $code === '') {
            return;
        }

        $this->chat->aborted($game, $actorUserId, $battle, $current['type'], $current['id'], $code);
    }
}
