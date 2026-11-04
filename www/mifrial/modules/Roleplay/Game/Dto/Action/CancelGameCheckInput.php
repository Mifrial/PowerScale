<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.cancelCheck.
 */
final class CancelGameCheckInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param array $proposal Тело с actor.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $processId,
        public readonly string $idempotencyKey,
        public readonly array $proposal = [],
    ) {
    }
}
