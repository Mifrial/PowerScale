<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Строка process. Проверку и удар не считает.
 */
interface IGameProcesses
{
    /**
     * Пишет open текущей сессии или одного боя.
     *
     * @param int $gameId Игра.
     * @param int|null $battleId Бой или null.
     * @param string $participantType Тип character или npc.
     * @param int $participantId Участник.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если сессии нет, игра completed или участник пустой.
     * @throws GameNotFoundException Если игры, снимка, NPC или боя нет.
     */
    public function open(int $gameId, ?int $battleId, string $participantType, int $participantId): array;

    /**
     * Переводит open в resolved. Лист не пишет.
     *
     * @param int $processId Process.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если статус не open.
     */
    public function resolve(int $processId): array;

    /**
     * Переводит одну строку open в cancelled.
     *
     * @param int $processId Process.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если статус не open.
     */
    public function cancel(int $processId): array;

    /**
     * Гасит open персонажа текущей сессии. Сессии нет — пустой проход.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForCharacter(int $gameId, int $characterId): void;

    /**
     * Гасит open одного боя.
     *
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForBattle(int $battleId): void;

    /**
     * Гасит оставшиеся open сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForSession(int $sessionId): void;
}
