<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Бой текущей сессии: проверка, состав и сессии процессов.
 */
final class GameBattleProjection
{
    /**
     * Создаёт чтение.
     *
     * @param GameSessionRepository $sessions Сессии.
     * @param GameBattleRepository $battles Бои.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeSheets $strikeSheets Снимки удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSessionRepository $sessions,
        private readonly GameBattleRepository $battles,
        private readonly GameProcessFlow $processes,
        private readonly GameStrikeSheets $strikeSheets,
    ) {
    }

    /**
     * Бой текущей сессии.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если сессии нет или бой чужой.
     */
    public function require(int $gameId, int $battleId): void
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        $battle = $this->battles->find($battleId);
        if ($sessionId === null || $battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException();
        }
    }

    /**
     * Состав боя.
     *
     * @param int $battleId Бой.
     *
     * @return list<array{type: string, id: int}> Пары.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function participants(int $battleId): array
    {
        return $this->battles->participants->find($battleId);
    }

    /**
     * Сессии процессов и слоты долга участников боя.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     *
     * @return array{processSessions: array<string, array<string, mixed>>, actionDebt: array<string, mixed>} Состояние.
     */
    public function processes(GameRecord $game, int $battleId): array
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null) {
            return ['processSessions' => [], 'actionDebt' => []];
        }

        $sessions = [];
        foreach ($this->battles->participants->find($battleId) as $participant) {
            $type = $participant['type'];
            $id = $participant['id'];
            $view = $this->processes->view->view(
                $game,
                $battle,
                ['type' => $type, 'id' => $id],
                $this->strikeSheets->abilityLevels($game, $type, $id),
            );
            if ($view !== null) {
                $sessions[$type . ':' . $id] = $view;
            }
        }

        return ['processSessions' => $sessions, 'actionDebt' => $battle->getActionDebt()];
    }
}
