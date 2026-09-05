<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Chat\Service\ChatHttpService;

/**
 * Пометка чата прочитанным.
 */
final class MarkChatReadAction implements IActionHandler
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
     * Ставит last_read.
     *
     * @param int $chatId Чат.
     *
     * @return null Успех без data.
     */
    public function handle(int $chatId): mixed
    {
        return $this->chatHttpService->markChatRead($chatId);
    }
}
