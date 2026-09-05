<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Exception;

use Throwable;

/**
 * Набор полей или инвариант чата недопустим.
 */
final class ChatInvalidException extends ChatException
{
    /**
     * Создаёт ошибку значений чата.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Chat values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('CHAT_INVALID', $message, $previous);
    }
}
