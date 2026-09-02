<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Exception;

use Throwable;

/**
 * Отказ входа, регистрации или политики входа.
 */
final class AuthInvalidException extends AuthException
{
    /**
     * Создаёт AUTH_INVALID.
     *
     * @param string $message Формулировка без различия причин входа.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Authentication failed',
        ?Throwable $previous = null,
    ) {
        parent::__construct('AUTH_INVALID', $message, $previous);
    }
}
