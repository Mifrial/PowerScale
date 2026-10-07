<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.get.
 */
final class GetGameInput implements IActionInput
{
    /**
     * Собирает вход get.
     *
     * @param int $id Игра.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
    ) {
    }
}
