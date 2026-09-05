<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Chat\Dto\Action\AddPrivateChatInput;
use Mifrial\Messages\Chat\Service\ChatHttpService;

/**
 * Создание или повтор private-пары.
 */
final class AddPrivateChatAction implements IActionHandler
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
     * Возвращает Chat.
     *
     * @param AddPrivateChatInput $input Вторая учётка.
     *
     * @return array<string, mixed> Чат.
     */
    public function handle(AddPrivateChatInput $input): array
    {
        return $this->chatHttpService->addPrivate($input);
    }
}
