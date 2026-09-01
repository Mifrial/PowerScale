<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Exception;

use Throwable;

/**
 * Ожидаемый отказ действия с машиночитаемым кодом.
 */
class ActionException extends MifrialException
{
    /**
     * Создаёт доменную ошибку действия с машиночитаемым кодом.
     *
     * @param string $errorCode Код ошибки для конверта ответа.
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
     * Возвращает код ошибки действия.
     *
     * @return string Машиночитаемый код.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
