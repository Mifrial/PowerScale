<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Exception;

use Throwable;

/**
 * Вход или инвариант кластера недопустимы.
 */
final class SpaceInvalidException extends SpaceException
{
    /**
     * Создаёт ошибку недопустимого входа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Space values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('SPACE_INVALID', $message, $previous);
    }
}
