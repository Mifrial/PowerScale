<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Exception;

use Throwable;

/**
 * Инфраструктурная ошибка ядра с кодом.
 */
class KernelException extends MifrialException
{
    /**
     * Создаёт ошибку ядра с машиночитаемым кодом.
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
     * Возвращает код ошибки ядра.
     *
     * @return string Машиночитаемый код.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
