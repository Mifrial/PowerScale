<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход `lore.importWorld`.
 */
final class ImportLoreWorldInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param array<string|int, mixed> $payload Документ `powerscale.lore`.
     * @param OptionalString $mode New или merge.
     * @param OptionalString $targetWorldCode Мир для merge.
     * @param OptionalString $conflictPolicy Skip или overwrite.
     * @param bool $dryRun Только отчёт.
     *
     * @return void
     */
    public function __construct(
        public readonly array $payload,
        public readonly OptionalString $mode,
        public readonly OptionalString $targetWorldCode,
        public readonly OptionalString $conflictPolicy,
        public readonly bool $dryRun = false,
    ) {
    }
}
