<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.createNpc.
 */
final class CreateGameNpcInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array<string, mixed> $visibility Видимость.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $name,
        public readonly array $visibility,
    ) {
    }
}
