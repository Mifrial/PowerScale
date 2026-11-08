<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\CreateNoteInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Создание заметки.
 */
final class CreateNoteAction implements IActionHandler
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
     * Возвращает созданную заметку.
     *
     * @param CreateNoteInput $input Поля.
     *
     * @return array<string, mixed> JSON.
     */
    public function handle(CreateNoteInput $input): array
    {
        return $this->noteHttpService->create($input);
    }
}
