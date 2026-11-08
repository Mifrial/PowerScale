<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\FindNotePageInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Страница заметок.
 */
final class FindNotePageAction implements IActionHandler
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
     * Возвращает items и total.
     *
     * @param FindNotePageInput $input Фильтр.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function handle(FindNotePageInput $input): array
    {
        return $this->noteHttpService->findPage($input);
    }
}
