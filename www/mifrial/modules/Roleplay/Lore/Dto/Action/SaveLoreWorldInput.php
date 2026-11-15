<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход `lore.saveWorld`.
 */
final class SaveLoreWorldInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param array<string|int, mixed> $world Полный снимок.
     * @param OptionalInt $version Версия клиента; нет поля — создание.
     *
     * @return void
     */
    public function __construct(
        public readonly array $world,
        public readonly OptionalInt $version,
    ) {
    }
}
