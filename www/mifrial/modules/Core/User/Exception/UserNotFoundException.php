<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Exception;

use Throwable;

/**
 * Строка учётки не найдена.
 */
final class UserNotFoundException extends UserException
{
    /**
     * Создаёт ошибку отсутствия учётки.
     *
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('USER_NOT_FOUND', 'User was not found', $previous);
    }
}
