<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Обновление заметки: null — поле не менять.
 */
final class UpdateNoteInput implements IActionInput
{
    /**
     * Собирает вход note.update.
     *
     * @param int $noteId Заметка.
     * @param string|null $title Заголовок.
     * @param string|null $bodyHtml HTML.
     * @param bool|null $pinned Закрепление.
     *
     * @return void
     */
    public function __construct(
        public readonly int $noteId,
        public readonly ?string $title = null,
        public readonly ?string $bodyHtml = null,
        public readonly ?bool $pinned = null,
    ) {
    }
}
