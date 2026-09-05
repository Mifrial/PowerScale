<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Exception;

use Throwable;

/**
 * Код, имя, описание или версия хендлера недопустимы.
 */
final class MechanicInvalidException extends MechanicException
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
        string $message = 'Mechanic values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('MECHANIC_INVALID', $message, $previous);
    }
}
