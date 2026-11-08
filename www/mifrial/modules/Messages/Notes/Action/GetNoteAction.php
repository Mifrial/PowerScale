<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\GetNoteInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Одна заметка.
 */
final class GetNoteAction implements IActionHandler
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
     * Возвращает заметку.
     *
     * @param GetNoteInput $input Заметка.
     *
     * @return array<string, mixed> JSON.
     */
    public function handle(GetNoteInput $input): array
    {
        return $this->noteHttpService->get($input);
    }
}
