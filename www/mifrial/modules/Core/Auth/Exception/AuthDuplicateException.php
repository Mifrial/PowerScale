<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Exception;

use Throwable;

/**
 * Дубль login или email при регистрации.
 */
final class AuthDuplicateException extends AuthException
{
    /**
     * Создаёт AUTH_DUPLICATE.
     *
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('AUTH_DUPLICATE', 'Account already exists', $previous);
    }
}
