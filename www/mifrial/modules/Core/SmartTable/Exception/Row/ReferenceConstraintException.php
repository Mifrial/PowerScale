<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Row;

use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Throwable;

/**
 * Нарушение внешнего ключа: нет родителя или есть ссылки.
 */
final class ReferenceConstraintException extends SmartTableException
{
    /**
     * Создаёт ошибку reference.
     *
     * @param Throwable|null $previous Исходный throwable драйвера.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('REFERENCE_CONSTRAINT', 'Reference constraint failed', $previous);
    }
}
