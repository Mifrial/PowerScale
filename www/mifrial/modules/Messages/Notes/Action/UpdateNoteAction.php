<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\UpdateNoteInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Обновление заметки.
 */
final class UpdateNoteAction implements IActionHandler
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
     * Возвращает обновлённую заметку.
     *
     * @param UpdateNoteInput $input Поля.
     *
     * @return array<string, mixed> JSON.
     */
    public function handle(UpdateNoteInput $input): array
    {
        return $this->noteHttpService->update($input);
    }
}
