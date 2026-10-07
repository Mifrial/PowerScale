<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.getBattleSheets.
 */
final class GetGameBattleSheetsInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $battleId,
    ) {
    }
}
