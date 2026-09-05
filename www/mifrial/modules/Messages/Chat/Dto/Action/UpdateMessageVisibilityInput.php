<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Смена аудитории сообщения: чат, id, optional JSON visibility.
 */
final class UpdateMessageVisibilityInput implements IActionInput
{
    /**
     * Собирает вход updateMessageVisibility.
     *
     * @param int $chatId Чат.
     * @param int $messageId Сообщение.
     * @param mixed $visibility JSON видимости; нет ключа / null — всем.
     *
     * @return void
     */
    public function __construct(
        public readonly int $chatId,
        public readonly int $messageId,
        public readonly mixed $visibility = null,
    ) {
    }
}
