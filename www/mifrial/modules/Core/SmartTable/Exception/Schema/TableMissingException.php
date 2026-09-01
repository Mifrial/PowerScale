<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Schema;

/**
 * Физической таблицы нет.
 */
final class TableMissingException extends SchemaException
{
    /**
     * Создаёт ошибку отсутствующей таблицы.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('TABLE_MISSING', 'Table is missing');
    }
}
