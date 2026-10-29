<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.getActionOptions.
 */
final class GetGameActionOptionsInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param string $actorType Вид участника: character или npc.
     * @param int $actorId Идентификатор участника.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $actorType,
        public readonly int $actorId,
    ) {
    }
}
