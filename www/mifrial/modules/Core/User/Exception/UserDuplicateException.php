<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Exception;

use Throwable;

/**
 * Нарушение уникальности login или email.
 */
final class UserDuplicateException extends UserException
{
    /**
     * Создаёт ошибку дубля учётки.
     *
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('USER_DUPLICATE', 'User is duplicate', $previous);
    }
}
