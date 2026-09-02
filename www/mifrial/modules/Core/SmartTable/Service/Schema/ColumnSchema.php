<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Throwable;

/**
 * DDL колонок основной таблицы из ColumnMeta.
 */
final class ColumnSchema
{
    /**
     * Создаёт DDL колонок.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
    ) {
    }

    /**
     * Создаёт основную таблицу без индексов и sidecar.
     *
     * @param string $tableName Имя.
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createMainTable(string $tableName, SmartTableDefinition $tableDefinition): void
    {
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        try {
            $schemaBuilder->create($tableName, function (Blueprint $blueprint) use ($tableDefinition): void {
                foreach ($tableDefinition->getMap() as $field) {
                    if ($field->settings()->multiple()) {
                        continue;
                    }

                    $this->defineColumn($blueprint, $field);
                }
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Добавляет колонки карты, которых ещё нет, кроме id.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, string> $columnNames Колонки БД.
     *
     * @return void
     *
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function addMissingColumns(SmartTableDefinition $tableDefinition, array $columnNames): void
    {
        $missingFields = $this->missingDataFields($tableDefinition, $columnNames);
        if ($missingFields === []) {
            return;
        }

        $tableName = $tableDefinition->getName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        try {
            $schemaBuilder->table($tableName, function (Blueprint $blueprint) use ($missingFields): void {
                foreach ($missingFields as $field) {
                    $this->defineColumn($blueprint, $field);
                }
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Снимает колонки, которых нет в карте как обычные поля, кроме id.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropLeftover(SmartTableDefinition $tableDefinition): void
    {
        $tableName = $tableDefinition->getName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $leftoverNames = $this->leftoverColumnNames($tableDefinition, $schemaBuilder->getColumnListing($tableName));
        if ($leftoverNames === []) {
            return;
        }

        try {
            $schemaBuilder->table($tableName, function (Blueprint $blueprint) use ($leftoverNames): void {
                foreach ($leftoverNames as $columnName) {
                    $blueprint->dropColumn($columnName);
                }
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Добавляет колонку поля на Blueprint.
     *
     * @param Blueprint $blueprint Чертёж таблицы.
     * @param BaseField $field Поле карты.
     *
     * @return void
     *
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     */
    private function defineColumn(Blueprint $blueprint, BaseField $field): void
    {
        if ($field instanceof IdField) {
            $blueprint->integer('id', true, false);

            return;
        }

        $column = $this->blueprintColumn($blueprint, $field->name(), $field->column());
        if ($field->settings()->required()) {
            $column->nullable(false);

            return;
        }

        $column->nullable();
    }

    /**
     * Перечисляет поля карты без колонки, кроме id.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, string> $columnNames Колонки БД.
     *
     * @return array<int, BaseField> Поля к добавлению.
     */
    private function missingDataFields(SmartTableDefinition $tableDefinition, array $columnNames): array
    {
        $missingFields = [];
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if ($fieldName === 'id' || $field->settings()->multiple() || in_array($fieldName, $columnNames, true)) {
                continue;
            }

            $missingFields[] = $field;
        }

        return $missingFields;
    }

    /**
     * Имена колонок БД вне карты обычных полей.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, string> $columnNames Колонки БД.
     *
     * @return array<int, string> Лишние имена.
     */
    private function leftoverColumnNames(SmartTableDefinition $tableDefinition, array $columnNames): array
    {
        $mappedNames = [];
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if (!$field->settings()->multiple()) {
                $mappedNames[] = $fieldName;
            }
        }

        $leftoverNames = [];
        foreach ($columnNames as $columnName) {
            if ($columnName === 'id' || in_array($columnName, $mappedNames, true)) {
                continue;
            }

            $leftoverNames[] = $columnName;
        }

        return $leftoverNames;
    }

    /**
     * Строит колонку по ColumnMeta.
     *
     * @param Blueprint $blueprint Чертёж.
     * @param string $columnName Имя.
     * @param ColumnMeta $columnMeta Метаданные.
     *
     * @return Fluent Колонка Blueprint.
     *
     * @throws MapInvalidException Если тип неизвестен.
     */
    private function blueprintColumn(Blueprint $blueprint, string $columnName, ColumnMeta $columnMeta): Fluent
    {
        return match ($columnMeta->sqlType) {
            'VARCHAR' => $this->varcharColumn($blueprint, $columnName, $columnMeta),
            'TEXT' => $blueprint->text($columnName),
            'LONGTEXT' => $blueprint->longText($columnName),
            'INT' => $blueprint->integer($columnName),
            'TINYINT' => $this->tinyIntegerColumn($blueprint, $columnName, $columnMeta),
            'JSON' => $blueprint->json($columnName),
            default => throw new MapInvalidException('Unknown column sqlType'),
        };
    }

    /**
     * Строит VARCHAR с обязательной длиной.
     *
     * @param Blueprint $blueprint Чертёж.
     * @param string $columnName Имя.
     * @param ColumnMeta $columnMeta Метаданные.
     *
     * @return Fluent Колонка.
     *
     * @throws MapInvalidException Если длины нет.
     */
    private function varcharColumn(Blueprint $blueprint, string $columnName, ColumnMeta $columnMeta): Fluent
    {
        if ($columnMeta->length === null || $columnMeta->length < 1) {
            throw new MapInvalidException('VARCHAR requires length');
        }

        return $blueprint->string($columnName, $columnMeta->length);
    }

    /**
     * Строит TINYINT(1).
     *
     * @param Blueprint $blueprint Чертёж.
     * @param string $columnName Имя.
     * @param ColumnMeta $columnMeta Метаданные.
     *
     * @return Fluent Колонка.
     *
     * @throws MapInvalidException Если длина не 1.
     */
    private function tinyIntegerColumn(Blueprint $blueprint, string $columnName, ColumnMeta $columnMeta): Fluent
    {
        if ($columnMeta->length !== 1) {
            throw new MapInvalidException('TINYINT requires length 1');
        }

        return $blueprint->tinyInteger($columnName);
    }
}
