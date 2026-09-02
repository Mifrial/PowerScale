<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Exception;

use Throwable;

/**
 * Набор полей учётки недопустим.
 */
final class UserInvalidException extends UserException
{
    /**
     * Создаёт ошибку значений учётки.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'User values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('USER_INVALID', $message, $previous);
    }
}
