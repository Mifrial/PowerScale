<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Row;

use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Throwable;

/**
 * Нарушение уникальности колонки.
 */
final class UniqueConstraintException extends SmartTableException
{
    /**
     * Создаёт ошибку unique.
     *
     * @param Throwable|null $previous Исходный throwable драйвера.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('UNIQUE_CONSTRAINT', 'Unique constraint failed', $previous);
    }
}
