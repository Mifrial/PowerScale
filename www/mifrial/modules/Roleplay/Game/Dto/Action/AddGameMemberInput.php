<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.addMember.
 */
final class AddGameMemberInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $role Роль.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $userId,
        public readonly string $role,
    ) {
    }
}
