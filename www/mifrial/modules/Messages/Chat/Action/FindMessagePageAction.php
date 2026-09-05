<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Chat\Dto\Action\FindMessagePageInput;
use Mifrial\Messages\Chat\Service\ChatHttpService;

/**
 * Страница сообщений чата.
 */
final class FindMessagePageAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param ChatHttpService $chatHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly ChatHttpService $chatHttpService,
    ) {
    }

    /**
     * Возвращает items и total.
     *
     * @param FindMessagePageInput $input Страница.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function handle(FindMessagePageInput $input): array
    {
        return $this->chatHttpService->findMessagePage($input);
    }
}
