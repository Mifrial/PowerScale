<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.declareCheck.
 */
final class DeclareGameCheckInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $check Решение.
     * @param int|null $battleId Бой или null.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $idempotencyKey,
        public readonly int $expectedSheetVersion,
        public readonly array $check,
        public readonly ?int $battleId = null,
    ) {
    }
}
