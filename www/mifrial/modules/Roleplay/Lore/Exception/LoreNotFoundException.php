<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Exception;

use Throwable;

/**
 * Мир, раздел или статья кодекса не найдены.
 */
final class LoreNotFoundException extends LoreException
{
    /**
     * Создаёт ошибку отсутствия.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Lore row was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('LORE_NOT_FOUND', $message, $previous);
    }
}
