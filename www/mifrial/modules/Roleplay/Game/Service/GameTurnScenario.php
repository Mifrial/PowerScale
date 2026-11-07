<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Подготовка команды хода: бой, курсор и право текущего.
 */
final class GameTurnScenario
{
    /**
     * Создаёт подготовку.
     *
     * @param GameBattleLookup $battles Поиск боя.
     * @param GameTurnAccess $access Права.
     * @param GameTurnCursor $cursor Курсор хода.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleLookup $battles,
        private readonly GameTurnAccess $access,
        private readonly GameTurnCursor $cursor,
    ) {
    }

    /**
     * Находит бой и проверяет текущего.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param array<string, mixed> $body Тело.
     *
     * @return array{sessionId: int, battle: GameBattleRecord, current: array{type: string, id: int}, expectedBattle: int, expectedSheet: int} Подготовка.
     *
     * @throws GameInvalidException Если поля или порядок битые.
     * @throws GameNotFoundException Если боя нет или он чужой.
     */
    public function prepare(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        bool $editAll,
        array $body,
    ): array {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedBattleVersion'] ?? null;
        $expectedSheet = $body['expectedSheetVersion'] ?? null;
        if (!is_int($battleId) || !is_int($expectedBattle) || !is_int($expectedSheet)) {
            throw new GameInvalidException('Game turn field is invalid');
        }

        $battle = $this->battles->find($battleId, $sessionId);
        if ($battle->getTurnOrder() === null || $battle->getRound() < 1) {
            throw new GameInvalidException('Game turn order is missing');
        }

        $current = $this->cursor->current($battle);
        $this->access->assertActor($game, $actorUserId, $editAll, $current);

        return [
            'sessionId' => $sessionId,
            'battle' => $battle,
            'current' => $current,
            'expectedBattle' => $expectedBattle,
            'expectedSheet' => $expectedSheet,
        ];
    }
}
