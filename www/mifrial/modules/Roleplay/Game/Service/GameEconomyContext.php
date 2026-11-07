<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Параметры одного прохода экономики.
 */
final class GameEconomyContext
{
    /**
     * Создаёт контекст.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return void
     */
    public function __construct(
        public readonly GameRecord $game,
        public readonly int $actorUserId,
        public readonly bool $editAll,
        public readonly bool $viewAll,
    ) {
    }
}
