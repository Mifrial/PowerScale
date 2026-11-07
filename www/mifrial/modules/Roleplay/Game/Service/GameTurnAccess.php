<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Видимость игры, текущая сессия и право хода.
 */
final class GameTurnAccess
{
    /**
     * Создаёт проверки.
     *
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Карточка.
     * @param GameSessionRepository $sessions Сессия.
     * @param GameCharacterRepository $characters Персонажи.
     * @param GameMemberRepository $members Люди.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameSessionRepository $sessions,
        private readonly GameCharacterRepository $characters,
        private readonly GameMemberRepository $members,
    ) {
    }

    /**
     * Видимая игра.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return GameRecord Строка.
     *
     * @throws GameNotFoundException Если игра скрыта.
     */
    public function visible(int $gameId, int $actorUserId, bool $viewAll): GameRecord
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        return $game;
    }

    /**
     * Текущая сессия.
     *
     * @param int $gameId Игра.
     *
     * @return int Id сессии.
     *
     * @throws GameInvalidException Если сессии нет.
     */
    public function session(int $gameId): int
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game battle was not found');
        }

        return $sessionId;
    }

    /**
     * Владелец, gm или edit_all; для персонажа — его владелец.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param array{type: string, id: int} $current Текущий участник.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    public function assertActor(GameRecord $game, int $actorUserId, bool $editAll, array $current): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        if ($current['type'] !== 'character') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $row = $this->characters->getByPair($game->getId(), $current['id']);
        if ($row->getCharacterOwnerId() !== $actorUserId) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Роль gm.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool Да, если gm.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }
}
