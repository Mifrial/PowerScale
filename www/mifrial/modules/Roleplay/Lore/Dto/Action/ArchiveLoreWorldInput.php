<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход `lore.archiveWorld`.
 */
final class ArchiveLoreWorldInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $code Код мира.
     * @param bool $archived Архив.
     * @param OptionalInt $expectedVersion Версия клиента.
     *
     * @return void
     */
    public function __construct(
        public readonly string $code,
        public readonly bool $archived,
        public readonly OptionalInt $expectedVersion,
    ) {
    }
}
