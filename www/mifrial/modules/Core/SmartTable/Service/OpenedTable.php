<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\Query\TableList;
use Mifrial\Core\SmartTable\Service\Query\TableRows;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Сумка open: schema и records одной карты.
 */
final class OpenedTable implements IOpenedTable
{
    /**
     * Создаёт сумку.
     *
     * @param IOpenedSchema $openedSchema DDL.
     * @param IOpenedRecords $openedRecords Строки.
     *
     * @return void
     */
    private function __construct(
        private readonly IOpenedSchema $openedSchema,
        private readonly IOpenedRecords $openedRecords,
    ) {
    }

    /**
     * Связывает definition с исполнителями на одном кэше.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param TableSchema $tableSchema DDL.
     * @param TableRows $tableRows Строки.
     * @param TableList $tableList Список.
     * @param TableCache $tableCache Кэш.
     *
     * @return self Сумка.
     */
    public static function bind(
        SmartTableDefinition $tableDefinition,
        TableSchema $tableSchema,
        TableRows $tableRows,
        TableList $tableList,
        TableCache $tableCache,
    ): self {
        return new self(
            new OpenedSchema($tableDefinition, $tableSchema, $tableCache),
            new OpenedRecords($tableDefinition, $tableRows, $tableList, $tableCache, $tableSchema),
        );
    }

    /**
     * Возвращает порт DDL.
     *
     * @return IOpenedSchema Схема.
     */
    public function schema(): IOpenedSchema
    {
        return $this->openedSchema;
    }

    /**
     * Возвращает порт строк.
     *
     * @return IOpenedRecords Строки.
     */
    public function records(): IOpenedRecords
    {
        return $this->openedRecords;
    }
}
