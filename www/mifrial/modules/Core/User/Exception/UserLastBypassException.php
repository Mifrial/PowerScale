<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Exception;

use Throwable;

/**
 * Операция оставила бы ноль членств в активных bypass-группах.
 */
final class UserLastBypassException extends UserException
{
    /**
     * Создаёт ошибку последнего bypass.
     *
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('USER_LAST_BYPASS', 'Last bypass membership cannot be removed', $previous);
    }
}
