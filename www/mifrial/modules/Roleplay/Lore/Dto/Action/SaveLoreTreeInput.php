<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход `lore.saveTree`.
 */
final class SaveLoreTreeInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $code Код мира.
     * @param array<string|int, mixed> $sections Полное дерево.
     * @param OptionalInt $expectedWorldVersion Версия клиента.
     *
     * @return void
     */
    public function __construct(
        public readonly string $code,
        public readonly array $sections,
        public readonly OptionalInt $expectedWorldVersion,
    ) {
    }
}
