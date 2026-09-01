<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Schema;

/**
 * Карта полей не совпадает с колонками БД.
 */
final class SchemaMismatchException extends SchemaException
{
    /**
     * Создаёт ошибку расхождения схемы.
     *
     * @param string $message Уточнение.
     *
     * @return void
     */
    public function __construct(string $message = 'Table schema does not match field map')
    {
        parent::__construct('SCHEMA_MISMATCH', $message);
    }
}
