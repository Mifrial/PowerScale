<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Exception;

use Throwable;

/**
 * Чаты, членство или учётка для операции чата не найдены.
 */
final class ChatNotFoundException extends ChatException
{
    /**
     * Создаёт ошибку отсутствия.
     *
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('CHAT_NOT_FOUND', 'Chat was not found', $previous);
    }
}
