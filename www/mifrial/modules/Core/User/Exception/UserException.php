<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Exception;

use Mifrial\Core\Kernel\Exception\MifrialException;
use Throwable;

/**
 * База ошибок учётки: код плюс ветка наследников.
 */
abstract class UserException extends MifrialException
{
    /**
     * Создаёт ошибку User.
     *
     * @param string $errorCode Код ошибки.
     * @param string $message Текст без секретов.
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
     * Возвращает код ошибки User.
     *
     * @return string Машиночитаемый код.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
