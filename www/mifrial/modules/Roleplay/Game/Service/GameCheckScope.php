<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Interface\Service\IGameProcesses;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * Чтение игры и процессов для проверок.
 */
final class GameCheckScope
{
    /**
     * Создаёт область проверок.
     *
     * @param IGames $games Игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param IGameProcesses $processes Процессы.
     *
     * @return void
     */
    public function __construct(
        public readonly IGames $games,
        public readonly GameCardAccess $cardAccess,
        public readonly IGameProcesses $processes,
    ) {
    }
}
