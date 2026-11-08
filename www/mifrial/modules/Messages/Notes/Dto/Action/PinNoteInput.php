<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Закрепление заметки.
 */
final class PinNoteInput implements IActionInput
{
    /**
     * Собирает вход note.pin.
     *
     * @param int $noteId Заметка.
     * @param bool $pinned Новое значение.
     *
     * @return void
     */
    public function __construct(
        public readonly int $noteId,
        public readonly bool $pinned,
    ) {
    }
}
