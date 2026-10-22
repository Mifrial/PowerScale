<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;

/**
 * Права объявления и ответа удара.
 */
final class GameStrikeAccess
{
    /**
     * Создаёт проверку.
     *
     * @param GameMemberRepository $members Участники.
     * @param GameCharacterRepository $characters Персонажи игры.
     * @param GameBattleRepository $battles Бои.
     * @param GameCardAccess $cardAccess Видимость карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameMemberRepository $members,
        private readonly GameCharacterRepository $characters,
        private readonly GameBattleRepository $battles,
        private readonly GameCardAccess $cardAccess,
    ) {
    }

    /**
     * Видимость карточки или GAME_NOT_FOUND.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ.
     *
     * @return void
     *
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function assertVisible(GameRecord $game, int $actorUserId, bool $viewAll): void
    {
        if ($this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            return;
        }

        throw new GameNotFoundException('Game was not found');
    }

    /**
     * Атакующий и цели — разные участники состава.
     *
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $attacker Атакующий.
     * @param list<array{type: string, id: int}> $targets Цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если пара совпала или вид чужой.
     * @throws GameNotFoundException Если пары нет в составе.
     */
    public function assertRoster(int $battleId, array $attacker, array $targets): void
    {
        $this->assertKind($attacker['type'] ?? null);
        foreach ($targets as $target) {
            $this->assertKind($target['type'] ?? null);
            if ($target === $attacker) {
                throw new GameInvalidException('Game strike target is invalid');
            }
        }

        $roster = $this->battles->findParticipants($battleId);
        if (!in_array($attacker, $roster, true)) {
            throw new GameNotFoundException('Game battle participant was not found');
        }

        foreach ($targets as $target) {
            if (!in_array($target, $roster, true)) {
                throw new GameNotFoundException('Game battle participant was not found');
            }
        }
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
     * Вид участника.
     *
     * @param mixed $kind Вид.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertKind(mixed $kind): void
    {
        if ($kind !== 'character' && $kind !== 'npc') {
            throw new GameInvalidException('Game strike participant is invalid');
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
    public function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }
}
