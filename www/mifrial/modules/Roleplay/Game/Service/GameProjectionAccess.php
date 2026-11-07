<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * Доступ читателя к карточке, NPC и листу персонажа.
 */
final class GameProjectionAccess
{
    /**
     * Создаёт проверку.
     *
     * @param IGames $games Игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
    ) {
    }

    /**
     * Карточка видна.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если игры нет или карточка скрыта.
     */
    public function open(int $gameId, int $actorUserId, bool $viewAll): GameRecord
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException();
        }

        return $game;
    }

    /**
     * Роль участника или null.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return string|null gm, player или null.
     */
    public function roleOf(int $gameId, int $actorUserId): ?string
    {
        try {
            return $this->games->getMember($gameId, $actorUserId)->getRole();
        } catch (GameNotFoundException) {
            return null;
        }
    }

    /**
     * Владелец игры или gm.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если актор ведёт игру.
     */
    public function leading(GameRecord $game, int $actorUserId): bool
    {
        return $game->getOwnerId() === $actorUserId || $this->roleOf($game->getId(), $actorUserId) === 'gm';
    }

    /**
     * Scope пускает к NPC.
     *
     * @param GameRecord $game Игра.
     * @param GameNpcRecord $record Строка.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если NPC виден.
     */
    public function npcAdmitted(GameRecord $game, GameNpcRecord $record, int $actorUserId): bool
    {
        if ($this->leading($game, $actorUserId)) {
            return true;
        }

        return $this->scopeAllows($record->getVisibility(), $game->getId(), $actorUserId);
    }

    /**
     * Полный лист персонажа.
     *
     * @param GameRecord $game Игра.
     * @param GameCharacterRecord $row Строка.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если секции не режутся.
     */
    public function characterOpen(GameRecord $game, GameCharacterRecord $row, int $actorUserId): bool
    {
        return $row->getCharacterOwnerId() === $actorUserId || $this->leading($game, $actorUserId);
    }

    /**
     * Scope all или users. gm сюда не входит.
     *
     * @param array<string, mixed> $visibility Объект.
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если секции можно показать.
     */
    private function scopeAllows(array $visibility, int $gameId, int $actorUserId): bool
    {
        $scope = $visibility['scope'] ?? null;
        if ($scope === 'all') {
            return $this->roleOf($gameId, $actorUserId) !== null;
        }

        if ($scope !== 'users' || !is_array($visibility['userIds'] ?? null)) {
            return false;
        }

        return in_array($actorUserId, $visibility['userIds'], true);
    }
}
