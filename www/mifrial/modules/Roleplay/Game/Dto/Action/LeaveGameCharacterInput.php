<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.leaveCharacter.
 */
final class LeaveGameCharacterInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $membershipRevision Ожидаемая revision.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $characterId,
        public readonly int $membershipRevision,
    ) {
    }
}
