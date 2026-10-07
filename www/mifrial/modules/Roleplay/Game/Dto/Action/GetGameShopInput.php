<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.getShop.
 */
final class GetGameShopInput implements IActionInput
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
