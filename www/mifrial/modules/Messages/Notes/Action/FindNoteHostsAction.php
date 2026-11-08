<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Notes\Dto\Action\FindNoteHostsInput;
use Mifrial\Messages\Notes\Service\NoteHttpService;

/**
 * Сущности с заметками для левой колонки.
 */
final class FindNoteHostsAction implements IActionHandler
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
     * Возвращает группы.
     *
     * @param FindNoteHostsInput $input Фильтр.
     *
     * @return array{items: array<int, array<string, mixed>>} Группы.
     */
    public function handle(FindNoteHostsInput $input): array
    {
        return $this->noteHttpService->findHosts($input);
    }
}
