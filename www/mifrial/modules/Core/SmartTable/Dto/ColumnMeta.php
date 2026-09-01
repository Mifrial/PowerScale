<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Метаданные колонки для будущего DDL, не SQL.
 */
final class ColumnMeta
{
    /**
     * Создаёт описание колонки.
     *
     * @param string $sqlType Наш SQL-тип (VARCHAR, TEXT, …).
     * @param int|null $length Длина VARCHAR или null.
     *
     * @return void
     */
    public function __construct(
        public readonly string $sqlType,
        public readonly ?int $length = null,
    ) {
    }
}
