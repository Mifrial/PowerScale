<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Exception;

use Throwable;

/**
 * Поля, коды или границы кодекса недопустимы.
 */
final class LoreInvalidException extends LoreException
{
    /**
     * Создаёт ошибку значений.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Lore values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('LORE_INVALID', $message, $previous);
    }
}
