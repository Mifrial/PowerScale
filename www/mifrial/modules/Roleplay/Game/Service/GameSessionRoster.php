<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacterSessionParticipants;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Чтение текущей сессии. Старт и stop сюда не входят.
 */
final class GameSessionRoster implements IGameSessionRoster, ICharacterSessionParticipants
{
    /**
     * Создаёт чтение.
     *
     * @param GameSessionRepository $sessionRepository Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSessionRepository $sessionRepository,
    ) {
    }

    /**
     * Строка сессии есть.
     *
     * @param int $gameId Игра.
     *
     * @return bool true, если стол идёт.
     */
    public function hasSession(int $gameId): bool
    {
        return $this->sessionRepository->findSessionId($gameId) !== null;
    }

    /**
     * Пара вошла в эту сессию.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return bool true, если строка состава есть.
     */
    public function isParticipant(int $gameId, int $characterId): bool
    {
        $sessionId = $this->sessionRepository->findSessionId($gameId);
        if ($sessionId === null) {
            return false;
        }

        return $this->sessionRepository->hasParticipant($sessionId, $characterId);
    }

    /**
     * Персонаж есть в составе текущей сессии.
     *
     * @param int $characterId Персонаж.
     *
     * @return bool true, если migrate запрещён.
     */
    public function isActiveSessionParticipant(int $characterId): bool
    {
        return $this->sessionRepository->hasCharacter($characterId);
    }
}
