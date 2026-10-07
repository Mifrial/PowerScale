<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.setBattleRoster.
 */
final class SetGameBattleRosterInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param array $participants Состав.
     * @param int $expectedVersion Версия боя.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $battleId,
        public readonly string $idempotencyKey,
        public readonly array $participants,
        public readonly int $expectedVersion,
    ) {
    }
}
