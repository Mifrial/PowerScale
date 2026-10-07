<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.getSheets.
 */
final class GetGameSheetsInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param array<mixed> $keys Ключи.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly array $keys,
    ) {
    }
}
