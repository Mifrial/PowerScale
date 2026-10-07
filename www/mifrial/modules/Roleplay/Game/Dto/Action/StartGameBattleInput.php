<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.startBattle.
 */
final class StartGameBattleInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ повтора.
     * @param array $participants Состав.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $idempotencyKey,
        public readonly array $participants,
    ) {
    }
}
