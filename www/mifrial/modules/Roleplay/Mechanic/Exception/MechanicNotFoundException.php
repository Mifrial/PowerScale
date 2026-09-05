<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Exception;

use Throwable;

/**
 * Строка механики не найдена.
 */
final class MechanicNotFoundException extends MechanicException
{
    /**
     * Создаёт ошибку отсутствия строки.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Mechanic was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('MECHANIC_NOT_FOUND', $message, $previous);
    }
}
