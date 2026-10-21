<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;

/**
 * Права объявления и ответа широкого удара.
 */
final class GameWideStrikeAccess
{
    /**
     * Создаёт проверку.
     *
     * @param GameMemberRepository $members Участники.
     * @param GameCharacterRepository $characters Персонажи игры.
     *
     * @return void
     */
    public function __construct(
        private readonly GameMemberRepository $members,
        private readonly GameCharacterRepository $characters,
    ) {
    }

    /**
     * Владелец игры, gm или edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    public function assertWriter(GameRecord $game, int $actorUserId, bool $editAll): void
    {
        if ($this->isWriter($game, $actorUserId, $editAll)) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Писатель или владелец активного персонажа-атакующего.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array{type: string, id: int} $attacker Атакующий.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    public function assertAttacker(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $attacker,
    ): void {
        $this->assertSide($game, $actorUserId, $editAll, $attacker);
    }

    /**
     * Писатель или владелец активного персонажа-цели.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    public function assertTarget(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $target,
    ): void {
        $this->assertSide($game, $actorUserId, $editAll, $target);
    }

    /**
     * Владелец игры, gm или edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return bool Да, если писатель.
     */
    public function isWriter(GameRecord $game, int $actorUserId, bool $editAll): bool
    {
        return $game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId);
    }

    /**
     * Может ли актор ответить за эту сторону.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array{type: string, id: int} $side Участник.
     *
     * @return bool Да, если может.
     */
    public function canAnswer(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $side,
    ): bool {
        try {
            $this->assertSide($game, $actorUserId, $editAll, $side);
        } catch (ActionException) {
            return false;
        }

        return true;
    }

    /**
     * Писатель или владелец активного персонажа. NPC — только писатель.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array{type: string, id: int} $side Участник.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function assertSide(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $side,
    ): void {
        if ($this->isWriter($game, $actorUserId, $editAll)) {
            return;
        }

        $kind = $side['type'] ?? null;
        $id = $side['id'] ?? null;
        if (!is_string($kind) || !is_int($id) || $kind !== 'character') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        try {
            $row = $this->characters->getByPair($game->getId(), $id);
        } catch (GameNotFoundException) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        if ($row->getCharacterOwnerId() !== $actorUserId || $row->getStatus() !== 'active') {
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
