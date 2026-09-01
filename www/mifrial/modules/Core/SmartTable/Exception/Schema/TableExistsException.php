<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Schema;

/**
 * Физическая таблица уже есть.
 */
final class TableExistsException extends SchemaException
{
    /**
     * Создаёт ошибку существующей таблицы.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('TABLE_EXISTS', 'Table already exists');
    }
}
