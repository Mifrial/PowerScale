<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;

/**
 * Чтение текущей сессии и её состава. Запись сессии сюда не входит.
 */
interface IGameSessionRoster
{
    /**
     * Строка сессии этой игры есть.
     *
     * @param int $gameId Игра.
     *
     * @return bool true, если стол идёт.
     */
    public function hasSession(int $gameId): bool;

    /**
     * Пара игра+персонаж есть в составе текущей сессии.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return bool true, если персонаж вошёл в эту сессию.
     */
    public function isParticipant(int $gameId, int $characterId): bool;

    /**
     * Открытый бой текущей сессии или null.
     *
     * @param int $gameId Игра.
     *
     * @return GameBattleRecord|null Строка или null.
     */
    public function findOpenBattle(int $gameId): ?GameBattleRecord;
}
