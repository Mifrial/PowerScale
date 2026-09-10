<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Exception;

use Throwable;

/**
 * Запись журнала не найдена.
 */
final class LoggerNotFoundException extends LoggerException
{
    /**
     * Создаёт ошибку отсутствия строки.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Log row not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('LOGGER_NOT_FOUND', $message, $previous);
    }
}
