<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Chat\Dto\Action\SendMessageInput;
use Mifrial\Messages\Chat\Service\ChatHttpService;

/**
 * Отправка сообщения.
 */
final class SendMessageAction implements IActionHandler
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
     * Возвращает Message.
     *
     * @param SendMessageInput $input Send.
     *
     * @return array<string, mixed> Сообщение.
     */
    public function handle(SendMessageInput $input): array
    {
        return $this->chatHttpService->sendMessage($input);
    }
}
