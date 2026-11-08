<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\PinNoteInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Закрепление заметки.
 */
final class PinNoteAction implements IActionHandler
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
     * @param PinNoteInput $input Заметка и флаг.
     *
     * @return array<string, mixed> JSON.
     */
    public function handle(PinNoteInput $input): array
    {
        return $this->noteHttpService->pin($input);
    }
}
