<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\DeleteNoteInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Удаление заметки.
 */
final class DeleteNoteAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param NoteHttpService $noteHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly NoteHttpService $noteHttpService,
    ) {
    }

    /**
     * Возвращает подтверждение.
     *
     * @param DeleteNoteInput $input Заметка.
     *
     * @return array{deleted: true} Подтверждение.
     */
    public function handle(DeleteNoteInput $input): array
    {
        return $this->noteHttpService->delete($input);
    }
}
