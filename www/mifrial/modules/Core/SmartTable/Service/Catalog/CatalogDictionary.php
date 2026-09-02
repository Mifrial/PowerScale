<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Catalog;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\OpenedTable;
use Mifrial\Core\SmartTable\Service\Query\TableList;
use Mifrial\Core\SmartTable\Service\Query\TableRows;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\MetaFieldDefinition;
use Mifrial\Core\SmartTable\Table\MetaTableDefinition;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Строки st_meta_table / st_meta_field через OpenedTable.
 */
final class CatalogDictionary
{
    /**
     * Создаёт доступ к meta-строкам.
     *
     * @param TableSchema $tableSchema DDL.
     * @param TableRows $tableRows Строки.
     * @param TableList $tableList Списки.
     * @param TableCache $tableCache Кэш get/getList.
     *
     * @return void
     */
    public function __construct(
        private readonly TableSchema $tableSchema,
        private readonly TableRows $tableRows,
        private readonly TableList $tableList,
        private readonly TableCache $tableCache,
    ) {
    }

    /**
     * Строка словаря таблиц или null.
     *
     * @param string $tableName Имя.
     *
     * @return array<string, mixed>|null Строка.
     *
     * @throws TableMissingException Если meta-таблицы нет.
     */
    public function findTable(string $tableName): ?array
    {
        $rows = $this->tablesHandle()->getList(ListQuery::fromOptions([
            'filter' => ['name' => $tableName],
            'limit' => 1,
        ]))->rows();

        return $rows[0] ?? null;
    }

    /**
     * Строка словаря таблиц.
     *
     * @param string $tableName Имя.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws TableMissingException Если строки нет.
     */
    public function requireTable(string $tableName): array
    {
        $metaRow = $this->findTable($tableName);
        if ($metaRow === null) {
            throw new TableMissingException();
        }

        return $metaRow;
    }

    /**
     * Пишет таблицу и поля словаря.
     *
     * @param string $tableName Имя.
     * @param array<int, array<string, mixed>> $fieldSpecs Спеки.
     *
     * @return void
     *
     * @throws MapInvalidException Если спека некорректна.
     */
    public function insertTable(string $tableName, array $fieldSpecs): void
    {
        $tableId = $this->tablesHandle()->add(['name' => $tableName]);
        foreach ($fieldSpecs as $fieldSpec) {
            $this->fieldsHandle()->add($this->fieldRow($tableId, $fieldSpec));
        }
    }

    /**
     * Добавляет строку поля.
     *
     * @param int $tableId Id таблицы словаря.
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return void
     *
     * @throws MapInvalidException Если name/type не строки.
     */
    public function addFieldRow(int $tableId, array $fieldSpec): void
    {
        $this->fieldsHandle()->add($this->fieldRow($tableId, $fieldSpec));
    }

    /**
     * Поля словаря одной таблицы по id ASC.
     *
     * @param int $tableId Id st_meta_table.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws TableMissingException Если meta-полей нет.
     */
    public function fieldRows(int $tableId): array
    {
        return $this->fieldsHandle()->getList(ListQuery::fromOptions([
            'filter' => ['table_id' => $tableId],
            'sort' => ['id' => 'ASC'],
            'limit' => 500,
        ]))->rows();
    }

    /**
     * Удаляет строку поля по имени.
     *
     * @param int $tableId Id таблицы словаря.
     * @param string $fieldName Имя поля.
     *
     * @return void
     *
     * @throws MapInvalidException Если строки поля нет.
     * @throws TableMissingException Если meta-полей нет.
     */
    public function deleteFieldRow(int $tableId, string $fieldName): void
    {
        foreach ($this->fieldRows($tableId) as $fieldRow) {
            if ($fieldRow['name'] === $fieldName) {
                $this->fieldsHandle()->delete((int) $fieldRow['id']);

                return;
            }
        }

        throw new MapInvalidException('Dictionary field is invalid');
    }

    /**
     * Удаляет поля и строку таблицы словаря.
     *
     * @param int $tableId Id таблицы словаря.
     *
     * @return void
     *
     * @throws TableMissingException Если meta нет.
     */
    public function deleteTable(int $tableId): void
    {
        foreach ($this->fieldRows($tableId) as $fieldRow) {
            $this->fieldsHandle()->delete((int) $fieldRow['id']);
        }

        $this->tablesHandle()->delete($tableId);
    }

    /**
     * Строка st_meta_field.
     *
     * @param int $tableId Id таблицы словаря.
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return array<string, mixed> Значения add.
     *
     * @throws MapInvalidException Если name/type не строки.
     */
    private function fieldRow(int $tableId, array $fieldSpec): array
    {
        $fieldName = $fieldSpec['name'] ?? null;
        $fieldType = $fieldSpec['type'] ?? null;
        if (!is_string($fieldName) || !is_string($fieldType)) {
            throw new MapInvalidException('Field spec name or type is invalid');
        }

        $settings = $fieldSpec;
        unset($settings['name'], $settings['type']);

        return [
            'table_id' => $tableId,
            'name' => $fieldName,
            'type' => $fieldType,
            'settings' => $settings,
        ];
    }

    /**
     * Открывает handle таблицы словаря таблиц.
     *
     * @return IOpenedTable Handle.
     */
    private function tablesHandle(): IOpenedTable
    {
        return $this->openHandle(new MetaTableDefinition());
    }

    /**
     * Открывает handle таблицы словаря полей.
     *
     * @return IOpenedTable Handle.
     */
    private function fieldsHandle(): IOpenedTable
    {
        return $this->openHandle(new MetaFieldDefinition());
    }

    /**
     * Открывает handle meta-таблицы (PHP-класс).
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return IOpenedTable Handle.
     */
    private function openHandle(SmartTableDefinition $tableDefinition): IOpenedTable
    {
        return new OpenedTable(
            $tableDefinition,
            $this->tableSchema,
            $this->tableRows,
            $this->tableList,
            $this->tableCache,
        );
    }
}
