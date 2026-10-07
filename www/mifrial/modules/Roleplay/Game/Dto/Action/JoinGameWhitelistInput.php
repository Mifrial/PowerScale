<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.joinWhitelist.
 */
final class JoinGameWhitelistInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
    ) {
    }
}
