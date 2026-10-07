<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.getInvitations.
 */
final class GetGameInvitationsInput implements IActionInput
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
