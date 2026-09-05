<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Отправка сообщения: чат, текст, opaque-вложения.
 */
final class SendMessageInput implements IActionInput
{
    /**
     * Собирает вход sendMessage.
     *
     * @param int $chatId Чат.
     * @param string $content Текст.
     * @param array<int, mixed> $attachments Вложения; нет ключа → [].
     * @param mixed $visibility JSON видимости; нет ключа / null — всем.
     *
     * @return void
     */
    public function __construct(
        public readonly int $chatId,
        public readonly string $content,
        public readonly array $attachments = [],
        public readonly mixed $visibility = null,
    ) {
    }
}
