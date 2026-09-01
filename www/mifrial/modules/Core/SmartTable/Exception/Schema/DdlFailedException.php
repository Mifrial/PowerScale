<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Schema;

use Throwable;

/**
 * Драйвер отклонил DDL.
 */
final class DdlFailedException extends SchemaException
{
    /**
     * Создаёт ошибку DDL.
     *
     * @param Throwable|null $previous Исходный throwable драйвера.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('DDL_FAILED', 'Table DDL failed', $previous);
    }
}
