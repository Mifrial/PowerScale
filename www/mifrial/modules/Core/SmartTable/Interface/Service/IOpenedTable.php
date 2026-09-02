<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Service;

/**
 * Сумка после open: схема и строки одной карты.
 */
interface IOpenedTable
{
    /**
     * Возвращает порт DDL этой карты.
     *
     * @return IOpenedSchema Схема.
     */
    public function schema(): IOpenedSchema;

    /**
     * Возвращает порт строк этой карты.
     *
     * @return IOpenedRecords Строки.
     */
    public function records(): IOpenedRecords;
}
