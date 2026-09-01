<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Row;

use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Throwable;

/**
 * Драйвер отклонил запись строки.
 */
final class RowWriteFailedException extends SmartTableException
{
    /**
     * Создаёт ошибку записи.
     *
     * @param Throwable|null $previous Исходный throwable драйвера.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('ROW_WRITE_FAILED', 'Row write failed', $previous);
    }
}
