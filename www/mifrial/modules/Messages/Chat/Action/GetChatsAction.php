<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Chat\Service\ChatHttpService;

/**
 * Список своих чатов.
 */
final class GetChatsAction implements IActionHandler
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
     * Возвращает Chat[].
     *
     * @return array<int, array<string, mixed>> Чаты.
     */
    public function handle(): array
    {
        return $this->chatHttpService->getChats();
    }
}
