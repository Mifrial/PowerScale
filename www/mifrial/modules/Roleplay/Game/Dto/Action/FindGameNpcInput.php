<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.findNpc: NPC по одному id, без игры.
 */
final class FindGameNpcInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $npcId NPC.
     *
     * @return void
     */
    public function __construct(
        public readonly int $npcId,
    ) {
    }
}
