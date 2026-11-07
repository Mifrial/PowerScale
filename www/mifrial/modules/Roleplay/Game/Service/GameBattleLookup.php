<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Бой, принадлежащий открытой сессии.
 */
final class GameBattleLookup
{
    /**
     * Создаёт поиск.
     *
     * @param GameBattleRepository $battles Строки боя.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
    ) {
    }

    /**
     * Бой этой сессии.
     *
     * @param int $battleId Бой.
     * @param int $sessionId Сессия.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если боя нет или он чужой.
     * @throws GameInvalidException Если строка битая.
     */
    public function find(int $battleId, int $sessionId): GameBattleRecord
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        return $battle;
    }
}
