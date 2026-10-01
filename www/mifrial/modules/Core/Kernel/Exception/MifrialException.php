<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Exception;

use RuntimeException;
use Throwable;

/**
 * Базовый тип исключений кода Mifrial со строковым кодом.
 */
class MifrialException extends RuntimeException
{
    /**
     * Создаёт исключение с машиночитаемым кодом.
     *
     * @param string $errorCode Код ошибки.
     * @param string $message Текст ошибки.
     * @param Throwable|null $previous Предыдущее исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Возвращает машиночитаемый код ошибки.
     *
     * @return string Код.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Дополнительные поля конверта ошибки.
     *
     * @return array<string, mixed> Пусто, если уточнений нет.
     */
    public function getErrorDetails(): array
    {
        return [];
    }
}
