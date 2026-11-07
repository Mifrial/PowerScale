<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Видимость, права и текущая сессия боевой команды.
 */
final class GameBattleAccess
{
    /**
     * Создаёт проверки.
     *
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameMemberRepository $members Люди.
     * @param GameSessionRepository $sessions Сессия.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameMemberRepository $members,
        private readonly GameSessionRepository $sessions,
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
     * @throws GameNotFoundException Если скрыта.
     */
    public function visible(int $gameId, int $actorUserId, bool $viewAll): GameRecord
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException();
        }

        return $game;
    }

    /**
     * completed не пишет.
     *
     * @param GameRecord $game Игра.
     *
     * @return void
     *
     * @throws GameInvalidException Если игра завершена.
     */
    public function assertOpen(GameRecord $game): void
    {
        if ($game->getStatus() === 'completed') {
            throw new GameInvalidException('Game is completed');
        }
    }

    /**
     * Владелец, gm или edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    public function assertWriter(GameRecord $game, int $actorUserId, bool $editAll): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Текущая сессия.
     *
     * @param int $gameId Игра.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если сессии нет.
     */
    public function session(int $gameId): int
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game session is not running');
        }

        return $sessionId;
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
            $member = $this->members->getByPair($gameId, $actorUserId);
        } catch (GameNotFoundException) {
            return false;
        }

        return $member->getRole() === 'gm';
    }
}
