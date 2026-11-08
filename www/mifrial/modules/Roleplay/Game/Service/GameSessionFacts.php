<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Что сейчас открыто на столе: сессия, бой и удар.
 */
final class GameSessionFacts
{
    /**
     * Создаёт чтение открытого состояния.
     *
     * @param IGameSessionRoster $sessionRoster Признак сессии и бой.
     * @param GameActionRepository $actions Открытый удар.
     *
     * @return void
     */
    public function __construct(
        private readonly IGameSessionRoster $sessionRoster,
        private readonly GameActionRepository $actions,
    ) {
    }

    /**
     * Идёт ли сессия этой игры.
     *
     * @param int $gameId Игра.
     *
     * @return bool true, если сессия есть.
     */
    public function hasSession(int $gameId): bool
    {
        return $this->sessionRoster->hasSession($gameId);
    }

    /**
     * Открытый бой текущей сессии.
     *
     * @param int $gameId Игра.
     *
     * @return GameBattleRecord|null Строка или null.
     */
    public function openBattle(int $gameId): ?GameBattleRecord
    {
        return $this->sessionRoster->findOpenBattle($gameId);
    }

    /**
     * Открытый удар 1→1 боя.
     *
     * @param GameBattleRecord|null $battle Бой или null.
     *
     * @return array<string, mixed>|null Колонки действия или null.
     */
    public function openStrike(?GameBattleRecord $battle): ?array
    {
        return $battle === null ? null : $this->actions->findOpenWithStrikes($battle->getId(), 'strike');
    }
}
