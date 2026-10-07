<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.resolveStrike.
 */
final class ResolveGameStrikeInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $defense Выбор.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $battleId,
        public readonly string $idempotencyKey,
        public readonly int $expectedVersion,
        public readonly int $expectedSheetVersion,
        public readonly array $defense,
    ) {
    }
}
