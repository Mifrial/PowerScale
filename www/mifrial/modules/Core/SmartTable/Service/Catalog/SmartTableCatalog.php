<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Catalog;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\TableExistsException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ITableCatalog;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\OpenedTable;
use Mifrial\Core\SmartTable\Service\Query\TableList;
use Mifrial\Core\SmartTable\Service\Query\TableRows;
use Mifrial\Core\SmartTable\Service\Schema\MfvSchema;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\MetaFieldDefinition;
use Mifrial\Core\SmartTable\Table\MetaTableDefinition;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Словарь runtime-таблиц: meta-строки и OpenedTable без Query Builder.
 */
final class SmartTableCatalog implements ITableCatalog
{
    private readonly CatalogDictionary $dictionary;

    /**
     * Создаёт каталог на schema/rows/list соединения.
     *
     * @param TableSchema $tableSchema DDL.
     * @param TableRows $tableRows Строки.
     * @param TableList $tableList Списки.
     * @param MfvSchema $mfvSchema Sidecar multiple.
     * @param TableCache $tableCache Кэш get/getList.
     *
     * @return void
     */
    public function __construct(
        private readonly TableSchema $tableSchema,
        private readonly TableRows $tableRows,
        private readonly TableList $tableList,
        private readonly MfvSchema $mfvSchema,
        private readonly TableCache $tableCache,
    ) {
        $this->dictionary = new CatalogDictionary(
            $tableSchema,
            $tableRows,
            $tableList,
            $tableCache,
        );
    }

    /**
     * Создаёт или дополняет физические meta-таблицы.
     *
     * @return void
     *
     * @throws TableExistsException Если create на уже существующей таблице.
     * @throws TableMissingException Если цели FK нет.
     * @throws MapInvalidException Если карта meta некорректна.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function installMeta(): void
    {
        $this->syncDefinition(new MetaTableDefinition());
        $this->syncDefinition(new MetaFieldDefinition());
    }

    /**
     * Карта runtime-таблицы для hop пути.
     *
     * @param string $tableName Физическое имя.
     *
     * @return SmartTableDefinition Карта.
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws MapInvalidException Если карта некорректна.
     */
    public function definitionByName(string $tableName): SmartTableDefinition
    {
        return $this->dictionary->runtimeDefinition($tableName);
    }

    /**
     * Открывает handle runtime-таблицы по строке словаря.
     *
     * @param string $tableName Физическое имя.
     *
     * @return IOpenedTable Handle.
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws MapInvalidException Если карта из словаря некорректна.
     */
    public function openByName(string $tableName): IOpenedTable
    {
        return $this->openHandle($this->dictionary->runtimeDefinition($tableName));
    }

    /**
     * Регистрирует таблицу в словаре и создаёт физику, если её ещё нет.
     *
     * @param string $tableName Физическое имя.
     * @param array<int, array<string, mixed>> $fieldSpecs Спеки полей без id.
     * @param array<int, array<int, string>> $uniqueKeys Составные unique.
     *
     * @return IOpenedTable Handle новой или поднятой таблицы.
     *
     * @throws TableExistsException Если физика уже есть.
     * @throws TableMissingException Если цели FK нет.
     * @throws MapInvalidException Если имя или спеки недопустимы.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createTable(
        string $tableName,
        array $fieldSpecs,
        array $uniqueKeys = [],
    ): IOpenedTable {
        if ($tableName === 'st_meta_table' || $tableName === 'st_meta_field') {
            throw new MapInvalidException('Dictionary table name is reserved');
        }

        $metaRow = $this->dictionary->findTable($tableName);
        if ($metaRow !== null) {
            return $this->createPhysicsFromDictionary($tableName);
        }

        $definition = (new FieldSpecAssembler())->makeDefinition($tableName, $fieldSpecs, $uniqueKeys);
        if ($this->tableSchema->hasPhysicalTable($definition->getName())) {
            throw new TableExistsException();
        }

        $this->dictionary->insertTable($tableName, $fieldSpecs, $uniqueKeys);
        $this->tableSchema->createTable($definition);
        $this->tableCache->noteDdl($tableName);

        return $this->openHandle($definition);
    }

    /**
     * Заменяет составные unique runtime-таблицы.
     *
     * @param string $tableName Физическое имя.
     * @param array<int, array<int, string>> $uniqueKeys Кортежи.
     *
     * @return void
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws MapInvalidException Если ключи некорректны.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function setUniqueKeys(string $tableName, array $uniqueKeys): void
    {
        $this->dictionary->updateUniqueKeys($tableName, $uniqueKeys);
        if ($this->tableSchema->hasPhysicalTable($tableName)) {
            $this->openByName($tableName)->schema()->updateTable();
        }
    }

    /**
     * Добавляет поле в словарь и колонку или sidecar.
     *
     * @param string $tableName Физическое имя.
     * @param array<string, mixed> $fieldSpec Спека поля.
     *
     * @return void
     *
     * @throws TableMissingException Если словаря или физики нет.
     * @throws MapInvalidException Если спека или имя недопустимы.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function addField(string $tableName, array $fieldSpec): void
    {
        $metaRow = $this->dictionary->requireTable($tableName);
        if (!$this->tableSchema->hasPhysicalTable($tableName)) {
            throw new TableMissingException();
        }

        (new FieldSpecAssembler())->assembleOne($fieldSpec);
        $this->assertNewFieldName($metaRow, $fieldSpec);
        $this->dictionary->addFieldRow((int) $metaRow['id'], $fieldSpec);
        $this->openByName($tableName)->schema()->updateTable();
    }

    /**
     * Удаляет поле из словаря и leftover хранения.
     *
     * @param string $tableName Физическое имя.
     * @param string $fieldName Имя поля.
     *
     * @return void
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws MapInvalidException Если поле id или неизвестно.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropField(string $tableName, string $fieldName): void
    {
        $metaRow = $this->dictionary->requireTable($tableName);
        $this->assertFieldNotInUniqueKeys($metaRow, $fieldName);
        $definition = $this->dictionary->runtimeDefinition($tableName);
        $fieldMap = $definition->getMap();
        if ($fieldName === 'id' || !isset($fieldMap[$fieldName])) {
            throw new MapInvalidException('Dictionary field is invalid');
        }

        $field = $fieldMap[$fieldName];
        if ($field->settings()->multiple()) {
            $this->mfvSchema->dropFieldStorage($definition, $field);
        }

        $this->dictionary->deleteFieldRow((int) $this->dictionary->requireTable($tableName)['id'], $fieldName);
        if ($this->tableSchema->hasPhysicalTable($tableName)) {
            $this->openByName($tableName)->schema()->forceUpdateTable();
        }
    }

    /**
     * Удаляет физику (если есть) и строки словаря.
     *
     * @param string $tableName Физическое имя.
     *
     * @return void
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws DdlFailedException Если входящий FK не дал DROP.
     * @throws MapInvalidException Если карта некорректна.
     */
    public function dropTable(string $tableName): void
    {
        $definition = $this->dictionary->runtimeDefinition($tableName);
        $metaRow = $this->dictionary->requireTable($tableName);
        if ($this->tableSchema->hasPhysicalTable($tableName)) {
            $this->tableSchema->deleteTable($definition);
        }

        $this->dictionary->deleteTable((int) $metaRow['id']);
        $this->tableCache->noteDdl($tableName);
    }

    /**
     * createTable или updateTable для meta-карты в PHP.
     *
     * @param SmartTableDefinition $tableDefinition Meta-определение.
     *
     * @return void
     *
     * @throws TableExistsException Если create на существующей таблице.
     * @throws TableMissingException Если цели FK нет.
     * @throws MapInvalidException Если карта некорректна.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function syncDefinition(SmartTableDefinition $tableDefinition): void
    {
        if ($this->tableSchema->hasPhysicalTable($tableDefinition->getName())) {
            $this->tableSchema->updateTable($tableDefinition);
            $this->tableCache->noteDdl($tableDefinition->getName());

            return;
        }

        $this->tableSchema->createTable($tableDefinition);
        $this->tableCache->noteDdl($tableDefinition->getName());
    }

    /**
     * Поднимает физику по уже записанному словарю, без второго insert.
     *
     * @param string $tableName Имя.
     *
     * @return IOpenedTable Handle.
     *
     * @throws TableExistsException Если физика уже есть.
     * @throws TableMissingException Если цели FK нет.
     * @throws MapInvalidException Если карта из словаря некорректна.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function createPhysicsFromDictionary(string $tableName): IOpenedTable
    {
        $definition = $this->dictionary->runtimeDefinition($tableName);
        if ($this->tableSchema->hasPhysicalTable($definition->getName())) {
            throw new TableExistsException();
        }

        $this->tableSchema->createTable($definition);
        $this->tableCache->noteDdl($tableName);

        return $this->openHandle($definition);
    }

    /**
     * Запрещает id и дубль имени поля.
     *
     * @param array<string, mixed> $metaRow Строка таблицы словаря.
     * @param array<string, mixed> $fieldSpec Спека.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя занято или id.
     * @throws TableMissingException Если meta-полей нет.
     */
    private function assertNewFieldName(array $metaRow, array $fieldSpec): void
    {
        $fieldName = $fieldSpec['name'] ?? null;
        if (!is_string($fieldName) || $fieldName === 'id') {
            throw new MapInvalidException('Field spec name or type is invalid');
        }

        foreach ($this->dictionary->fieldRows((int) $metaRow['id']) as $fieldRow) {
            if ($fieldRow['name'] === $fieldName) {
                throw new MapInvalidException('Duplicate field name');
            }
        }
    }

    /**
     * Поле не должно входить в unique_keys.
     *
     * @param array<string, mixed> $metaRow Строка таблицы словаря.
     * @param string $fieldName Имя поля.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле в составном unique.
     */
    private function assertFieldNotInUniqueKeys(array $metaRow, string $fieldName): void
    {
        $uniqueKeys = $metaRow['unique_keys'] ?? [];
        if ($uniqueKeys === null) {
            return;
        }

        if (!is_array($uniqueKeys)) {
            throw new MapInvalidException('Unique keys are invalid');
        }

        foreach ($uniqueKeys as $tuple) {
            if (is_array($tuple) && in_array($fieldName, $tuple, true)) {
                throw new MapInvalidException('Field is used in unique key');
            }
        }
    }

    /**
     * Handle runtime-таблицы.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return IOpenedTable Handle.
     */
    private function openHandle(SmartTableDefinition $tableDefinition): IOpenedTable
    {
        return OpenedTable::bind(
            $tableDefinition,
            $this->tableSchema,
            $this->tableRows,
            $this->tableList,
            $this->tableCache,
        );
    }
}
