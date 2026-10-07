<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Краткий roster и полный лист по ключам. Сессию и бой не пишет.
 */
interface IGameProjections
{
    /**
     * Краткие записи без листа.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return array<string, mixed> Персонажи и NPC.
     *
     * @throws GameNotFoundException Если карточка скрыта или персонажа нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function roster(int $gameId, int $actorUserId, bool $viewAll): array;

    /**
     * Полный лист названных ключей.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param array<mixed> $keys Список {type, id}.
     *
     * @return array<string, mixed> Листы и пропуски.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameNotFoundException Если карточка скрыта или персонажа нет.
     * @throws GameInvalidException Если ключ повторён или строка битая.
     */
    public function sheets(int $gameId, int $actorUserId, bool $viewAll, array $keys): array;

    /**
     * Краткие записи и листы состава одного боя.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     *
     * @return array<string, mixed> Состав и листы.
     *
     * @throws GameNotFoundException Если карточки, сессии или боя нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function battleSheets(int $gameId, int $actorUserId, bool $viewAll, int $battleId): array;
}
