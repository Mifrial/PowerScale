<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.declareStrike.
 */
final class DeclareGameStrikeInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Версия боя.
     * @param array $attack Выбор.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $battleId,
        public readonly string $idempotencyKey,
        public readonly int $expectedVersion,
        public readonly array $attack,
    ) {
    }
}
