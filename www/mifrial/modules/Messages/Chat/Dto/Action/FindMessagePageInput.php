<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Страница сообщений чата: chatId, limit, offset.
 */
final class FindMessagePageInput implements IActionInput
{
    /**
     * Собирает вход findMessagePage.
     *
     * @param int $chatId Чат.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     */
    public function __construct(
        public readonly int $chatId,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
