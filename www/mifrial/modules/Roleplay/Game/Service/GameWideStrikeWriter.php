<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;

/**
 * Право писать широкий удар: владелец, gm или edit_all.
 */
final class GameWideStrikeWriter
{
    /**
     * Создаёт проверку.
     *
     * @param GameMemberRepository $members Участники.
     *
     * @return void
     */
    public function __construct(
        private readonly GameMemberRepository $members,
    ) {
    }

    /**
     * Владелец, gm или edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    public function assert(GameRecord $game, int $actorUserId, bool $editAll): void
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
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }
}
