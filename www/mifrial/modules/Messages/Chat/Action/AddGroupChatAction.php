<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Chat\Dto\Action\AddGroupChatInput;
use Mifrial\Messages\Chat\Service\ChatHttpService;

/**
 * Создание группового чата.
 */
final class AddGroupChatAction implements IActionHandler
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
     * @param AddGroupChatInput $input Имя и члены.
     *
     * @return array<string, mixed> Чат.
     */
    public function handle(AddGroupChatInput $input): array
    {
        return $this->chatHttpService->addGroup($input);
    }
}
