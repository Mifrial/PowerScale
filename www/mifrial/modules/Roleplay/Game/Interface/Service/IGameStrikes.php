<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Один удар 1 → 1. Лист пишет только непустой список операций.
 */
interface IGameStrikes
{
    /**
     * Открывает удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array $attack Выбор.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED или INVALID_PARAMS.
     * @throws GameNotFoundException Если боя или карточки нет.
     * @throws GameInvalidException Если выбор или сессия.
     * @throws GameBattleConflictException Если версия или чужой ключ.
     */
    public function declareStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $attack,
    ): array;

    /**
     * Закрывает удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $defense Выбор.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED или INVALID_PARAMS.
     * @throws GameNotFoundException Если боя или карточки нет.
     * @throws GameInvalidException Если выбор или сессия.
     * @throws GameBattleConflictException Если версия или чужой ключ.
     */
    public function resolveStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $defense,
    ): array;
}
