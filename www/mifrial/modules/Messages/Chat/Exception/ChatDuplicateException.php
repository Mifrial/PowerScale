<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Exception;

use Throwable;

/**
 * Дубль членства или гонка addMember.
 */
final class ChatDuplicateException extends ChatException
{
    /**
     * Создаёт ошибку дубля.
     *
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('CHAT_DUPLICATE', 'Chat membership is duplicate', $previous);
    }
}
