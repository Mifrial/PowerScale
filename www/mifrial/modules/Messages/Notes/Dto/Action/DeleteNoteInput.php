<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Удаление заметки по id.
 */
final class DeleteNoteInput implements IActionInput
{
    /**
     * Собирает вход note.delete.
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
