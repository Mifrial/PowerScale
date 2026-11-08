<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Одна заметка по id.
 */
final class GetNoteInput implements IActionInput
{
    /**
     * Собирает вход note.get.
     *
     * @param int $noteId Заметка.
     *
     * @return void
     */
    public function __construct(
        public readonly int $noteId,
    ) {
    }
}
