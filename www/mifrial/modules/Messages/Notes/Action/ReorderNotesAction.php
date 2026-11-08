<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\ReorderNotesInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Ручной порядок заметок.
 */
final class ReorderNotesAction implements IActionHandler
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
     * Возвращает число заметок.
     *
     * @param ReorderNotesInput $input Порядок.
     *
     * @return array{reordered: int} Число.
     */
    public function handle(ReorderNotesInput $input): array
    {
        return $this->noteHttpService->reorder($input);
    }
}
