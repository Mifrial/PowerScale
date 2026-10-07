<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.respondInvitation.
 */
final class RespondGameInvitationInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $invitationId Приглашение.
     * @param string $action accept или decline.
     *
     * @return void
     */
    public function __construct(
        public readonly int $invitationId,
        public readonly string $action,
    ) {
    }
}
