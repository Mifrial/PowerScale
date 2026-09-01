<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Row;

use Mifrial\Core\SmartTable\Exception\SmartTableException;

/**
 * Строки с таким id нет.
 */
final class RowNotFoundException extends SmartTableException
{
    /**
     * Создаёт ошибку отсутствующей строки.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('ROW_NOT_FOUND', 'Row was not found');
    }
}
