<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Messages\Chat\Dto\Action\UpdateMessageVisibilityInput;
use Mifrial\Messages\Chat\Service\ChatHttpService;

/**
 * Смена аудитории сообщения.
 */
final class UpdateMessageVisibilityAction implements IActionHandler
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
     * @param UpdateMessageVisibilityInput $input Смена.
     *
     * @return array<string, mixed> Сообщение.
     */
    public function handle(UpdateMessageVisibilityInput $input): array
    {
        return $this->chatHttpService->updateMessageVisibility($input);
    }
}
