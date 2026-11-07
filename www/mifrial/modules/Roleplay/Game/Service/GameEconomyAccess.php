<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;

/**
 * Видимость карточки и право вести магазин.
 */
final class GameEconomyAccess
{
    /**
     * Создаёт проверку.
     *
     * @param IGames $games Строка игры.
     * @param GameMemberRepository $members Люди игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly GameMemberRepository $members,
        private readonly GameCardAccess $cardAccess,
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
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function visibleGame(int $gameId, int $actorUserId, bool $viewAll): GameRecord
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
     * @throws GameInvalidException Если завершена.
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
