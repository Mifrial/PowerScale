<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.proposeCheck.
 */
final class ProposeGameCheckInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $proposal Решение.
     * @param int|null $battleId Бой или null.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $idempotencyKey,
        public readonly int $expectedSheetVersion,
        public readonly array $proposal,
        public readonly ?int $battleId = null,
    ) {
    }
}
