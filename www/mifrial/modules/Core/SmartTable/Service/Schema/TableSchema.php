<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Schema;

use Illuminate\Database\Schema\Blueprint;
use JsonException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableExistsException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Throwable;

/**
 * Оркестрация DDL по определению таблицы.
 */
final class TableSchema
{
    /**
     * Создаёт синхронизатор схемы.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер модуля.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
    ) {
    }

    /**
     * Проверяет наличие физической таблицы по имени.
     *
     * @param string $tableName Имя.
     *
     * @return bool true, если таблица есть.
     */
    public function hasPhysicalTable(string $tableName): bool
    {
        return $this->databaseConnection->illuminateConnection()->getSchemaBuilder()->hasTable($tableName);
    }

    /**
     * Создаёт физическую таблицу.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableExistsException Если таблица уже есть.
     * @throws TableMissingException Если таблицы цели FK нет.
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createTable(SmartTableDefinition $tableDefinition): void
    {
        $tableName = $tableDefinition->getName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        if ($schemaBuilder->hasTable($tableName)) {
            throw new TableExistsException();
        }

        $referenceSchema = new ReferenceSchema($this->databaseConnection);
        $indexSchema = new IndexSchema($this->databaseConnection);
        $referenceSchema->assertTargetsExist($tableDefinition);
        $indexSchema->assertNames($tableDefinition);
        (new ColumnSchema($this->databaseConnection))->createMainTable($tableName, $tableDefinition);
        $this->createAccessories($tableDefinition, $indexSchema, $referenceSchema);
    }

    /**
     * Добавляет колонки, индексы, mfv и FK карты, которых ещё нет.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableMissingException Если своей таблицы или таблицы цели FK нет.
     * @throws SchemaMismatchException Если нет колонки id.
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function updateTable(SmartTableDefinition $tableDefinition): void
    {
        $this->addMissing($tableDefinition);
    }

    /**
     * Добавляет недостающее по карте и снимает leftover.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableMissingException Если своей таблицы или таблицы цели FK нет.
     * @throws SchemaMismatchException Если нет колонки id.
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function forceUpdateTable(SmartTableDefinition $tableDefinition): void
    {
        [$columnSchema, $indexSchema, $mfvSchema, $referenceSchema] = $this->addMissing($tableDefinition);
        $mfvSchema->dropLeftover($tableDefinition);
        $referenceSchema->dropLeftover($tableDefinition);
        $indexSchema->dropLeftover($tableDefinition);
        $columnSchema->dropLeftover($tableDefinition);
    }

    /**
     * Удаляет физическую таблицу и mfv полей карты.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableMissingException Если своей таблицы нет.
     * @throws MapInvalidException Если имя sidecar недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function deleteTable(SmartTableDefinition $tableDefinition): void
    {
        $tableName = $tableDefinition->getName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        if (!$schemaBuilder->hasTable($tableName)) {
            throw new TableMissingException();
        }

        (new ReferenceSchema($this->databaseConnection))->dropOwned($tableDefinition);
        (new MfvSchema($this->databaseConnection))->dropMapped($tableDefinition);
        try {
            $schemaBuilder->drop($tableName);
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Столы с FK CASCADE/SET NULL на этот PK (кэш детей после delete).
     *
     * @param string $parentTableName Физическое имя родителя.
     *
     * @return array<int, string> Имена детей, без дублей.
     */
    public function cacheDependentTableNames(string $parentTableName): array
    {
        try {
            $rows = $this->databaseConnection->illuminateConnection()->select(
                'select distinct table_name as child_table'
                . ' from information_schema.referential_constraints'
                . ' where constraint_schema = database()'
                . ' and referenced_table_name = ?'
                . ' and delete_rule in (\'CASCADE\', \'SET NULL\')',
                [$parentTableName],
            );
        } catch (Throwable) {
            return [];
        }

        $tableNames = [];
        foreach ($rows as $row) {
            $childName = is_object($row) ? ($row->child_table ?? null) : null;
            if (!is_string($childName) || $childName === '') {
                continue;
            }

            $tableNames[$childName] = true;
        }

        return array_keys($tableNames);
    }

    /**
     * Индексы, mfv и FK после CREATE колонок.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param IndexSchema $indexSchema Индексы.
     * @param ReferenceSchema $referenceSchema FK.
     *
     * @return void
     *
     * @throws TableMissingException Если цели FK нет.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function createAccessories(
        SmartTableDefinition $tableDefinition,
        IndexSchema $indexSchema,
        ReferenceSchema $referenceSchema,
    ): void {
        $indexSchema->createAll($tableDefinition);
        (new MfvSchema($this->databaseConnection))->createAll($tableDefinition);
        $referenceSchema->createAll($tableDefinition);
    }

    /**
     * Добавляет недостающие колонки, индексы, mfv и FK.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array{ColumnSchema, IndexSchema, MfvSchema, ReferenceSchema} Хелперы той же синхронизации.
     *
     * @throws TableMissingException Если своей таблицы или таблицы цели FK нет.
     * @throws SchemaMismatchException Если нет колонки id.
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function addMissing(SmartTableDefinition $tableDefinition): array
    {
        $this->assertTableReady($tableDefinition);
        $columnSchema = new ColumnSchema($this->databaseConnection);
        $indexSchema = new IndexSchema($this->databaseConnection);
        $mfvSchema = new MfvSchema($this->databaseConnection);
        $referenceSchema = new ReferenceSchema($this->databaseConnection);
        $referenceSchema->assertTargetsExist($tableDefinition);
        $indexSchema->assertNames($tableDefinition);
        $columnNames = $this->databaseConnection->illuminateConnection()
            ->getSchemaBuilder()
            ->getColumnListing($tableDefinition->getName());
        $columnSchema->addMissingColumns($tableDefinition, $columnNames);
        $indexSchema->createMissing($tableDefinition);
        $mfvSchema->createMissing($tableDefinition);
        $referenceSchema->createMissing($tableDefinition);

        return [$columnSchema, $indexSchema, $mfvSchema, $referenceSchema];
    }

    /**
     * Проверяет, что таблица есть и есть колонка id.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableMissingException Если своей таблицы нет.
     * @throws SchemaMismatchException Если нет колонки id.
     */
    private function assertTableReady(SmartTableDefinition $tableDefinition): void
    {
        $tableName = $tableDefinition->getName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        if (!$schemaBuilder->hasTable($tableName)) {
            throw new TableMissingException();
        }

        $columnNames = $schemaBuilder->getColumnListing($tableName);
        if (!in_array('id', $columnNames, true)) {
            throw new SchemaMismatchException('Existing table has no id column');
        }
    }

    /**
     * Копирует пару mechanic_id и mechanic_payload в mechanics и снимает старые колонки.
     *
     * @param string $tableName Таблица снимка.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     * @throws JsonException Если payload не кодируется.
     */
    public function foldLegacyMechanicColumns(string $tableName): void
    {
        $connection = $this->databaseConnection->illuminateConnection();
        $schemaBuilder = $connection->getSchemaBuilder();
        if (!$schemaBuilder->hasTable($tableName) || !$schemaBuilder->hasColumn($tableName, 'mechanic_id')) {
            return;
        }

        if (!$schemaBuilder->hasColumn($tableName, 'mechanics')) {
            return;
        }

        $rows = $connection->table($tableName)->get(['id', 'mechanic_id', 'mechanic_payload', 'mechanics']);
        foreach ($rows as $row) {
            if ($row->mechanics !== null) {
                continue;
            }

            $payload = $row->mechanic_payload;
            if (is_string($payload)) {
                $decodedPayload = json_decode($payload, true);
                $payload = is_array($decodedPayload) ? $decodedPayload : [];
            }

            if (!is_array($payload)) {
                $payload = [];
            }

            $list = $row->mechanic_id === null ? [] : [[
                'mechanic_id' => (int) $row->mechanic_id,
                'mechanic_payload' => $payload,
            ]];
            $connection->table($tableName)->where('id', $row->id)->update([
                'mechanics' => json_encode($list, JSON_THROW_ON_ERROR),
            ]);
        }

        try {
            $foreignKeys = $schemaBuilder->getForeignKeys($tableName);
            foreach ($foreignKeys as $foreignKey) {
                $columns = $foreignKey['columns'] ?? [];
                if (!is_array($columns) || !in_array('mechanic_id', $columns, true)) {
                    continue;
                }

                $constraintName = $foreignKey['name'] ?? null;
                if (!is_string($constraintName)) {
                    continue;
                }

                $schemaBuilder->table($tableName, function (Blueprint $blueprint) use ($constraintName): void {
                    $blueprint->dropForeign($constraintName);
                });
            }

            $schemaBuilder->table($tableName, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['mechanic_id', 'mechanic_payload']);
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }
}
