<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Exception;

use Throwable;

/**
 * Нет доступа к миру или статье кодекса.
 */
final class LoreDeniedException extends LoreException
{
    /**
     * Создаёт ошибку доступа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Lore access is denied',
        ?Throwable $previous = null,
    ) {
        parent::__construct('LORE_DENIED', $message, $previous);
    }
}
