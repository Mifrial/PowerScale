<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.setWhitelist.
 */
final class SetGameWhitelistInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param list<int> $userIds Id.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly array $userIds,
    ) {
    }
}
