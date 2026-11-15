<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход `lore.getWorld` и `lore.exportWorld`.
 */
final class GetLoreWorldInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $code Код мира.
     *
     * @return void
     */
    public function __construct(
        public readonly string $code,
    ) {
    }
}
