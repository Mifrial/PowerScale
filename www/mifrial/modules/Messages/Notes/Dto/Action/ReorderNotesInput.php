<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Ручной порядок заметок сущности.
 */
final class ReorderNotesInput implements IActionInput
{
    /**
     * Собирает вход note.reorder.
     *
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param array<int, int> $noteIds Полный список id.
     *
     * @return void
     */
    public function __construct(
        public readonly string $hostType,
        public readonly int $hostId,
        public readonly array $noteIds,
    ) {
    }
}
