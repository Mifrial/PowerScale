<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Exception;

use Throwable;

/**
 * Недопустимый вход просмотра журнала.
 */
final class LoggerInvalidException extends LoggerException
{
    /**
     * Создаёт ошибку входа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Logger query is invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('LOGGER_INVALID', $message, $previous);
    }
}
