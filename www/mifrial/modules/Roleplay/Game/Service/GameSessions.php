<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Exception\GameSessionConflictException;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Старт и stop одной текущей сессии. Статус кампании не меняет.
 */
final class GameSessions
{
    /**
     * Создаёт сценарий.
     *
     * @param IGames $games Игра.
     * @param IGameMemberships $memberships Строки персонажа.
     * @param ICharacters $characters Actual.
     * @param GameCharacterReview $review Допуск.
     * @param GameSessionRepository $sessionRepository Строки сессии.
     * @param GameBattleCleanup $battleCleanup Бои этой сессии.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly IGameMemberships $memberships,
        private readonly ICharacters $characters,
        private readonly GameCharacterReview $review,
        private readonly GameSessionRepository $sessionRepository,
        private readonly GameBattleCleanup $battleCleanup,
    ) {
    }

    /**
     * Открывает сессию и пишет допущенных.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws GameNotFoundException Если игры или actual нет.
     * @throws GameInvalidException Если completed или snapshot не сравнивается.
     * @throws GameSessionConflictException Если сессия уже есть.
     */
    public function start(int $gameId): void
    {
        $game = $this->requireOpenGame($gameId);
        if ($this->sessionRepository->findSessionId($gameId) !== null) {
            throw new GameSessionConflictException();
        }

        $this->sessionRepository->addSession($gameId, $this->admitted($game));
    }

    /**
     * Снимает состав и сессию.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если completed или сессии нет.
     */
    public function stop(int $gameId): void
    {
        $this->requireOpenGame($gameId);
        $sessionId = $this->sessionRepository->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game session is not running');
        }

        $this->battleCleanup->deleteWithSession($sessionId);
    }

    /**
     * Персонажи с истинным допуском.
     *
     * @param GameRecord $game Игра.
     *
     * @return list<int> Id.
     *
     * @throws GameNotFoundException Если actual нет.
     * @throws GameInvalidException Если snapshot не сравнивается.
     */
    private function admitted(GameRecord $game): array
    {
        $characterIds = [];
        foreach ($this->memberships->getListByGame($game->getId(), null) as $row) {
            if (!$this->review->canStartSession($game, $row, $this->actual($row->getCharacterId()))) {
                continue;
            }

            $characterIds[] = $row->getCharacterId();
        }

        return $characterIds;
    }

    /**
     * Actual или отказ игры.
     *
     * @param int $characterId Персонаж.
     *
     * @return CharacterRecord Лист.
     *
     * @throws GameNotFoundException Если листа нет.
     */
    private function actual(int $characterId): CharacterRecord
    {
        try {
            return $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }
    }

    /**
     * Игра есть и не completed.
     *
     * @param int $gameId Игра.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если completed.
     */
    private function requireOpenGame(int $gameId): GameRecord
    {
        $game = $this->games->get($gameId);
        if ($game->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }

        return $game;
    }
}
