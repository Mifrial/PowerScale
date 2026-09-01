<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Illuminate\Database\Schema\Blueprint;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Throwable;

/**
 * DDL одноколоночных index/unique из FieldSettings.
 */
final class IndexSchema
{
    /**
     * Создаёт DDL индексов.
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
     * Имя обычного индекса `{table}_{field}_idx`.
     *
     * @param SmartTableDefinition $tableDefinition Таблица.
     * @param BaseField $field Поле.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    public static function indexName(SmartTableDefinition $tableDefinition, BaseField $field): string
    {
        return self::physicalName($tableDefinition, $field, 'idx');
    }

    /**
     * Имя unique `{table}_{field}_unq`.
     *
     * @param SmartTableDefinition $tableDefinition Таблица.
     * @param BaseField $field Поле.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    public static function uniqueName(SmartTableDefinition $tableDefinition, BaseField $field): string
    {
        return self::physicalName($tableDefinition, $field, 'unq');
    }

    /**
     * Проверяет имена индексов карты до DDL.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя длиннее 64 или не шаблон.
     */
    public function assertNames(SmartTableDefinition $tableDefinition): void
    {
        foreach ($this->plannedIndexes($tableDefinition) as $plannedIndex) {
            self::physicalName($tableDefinition, $plannedIndex['field'], $plannedIndex['suffix']);
        }
    }

    /**
     * Создаёт все индексы карты.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createAll(SmartTableDefinition $tableDefinition): void
    {
        foreach ($this->plannedIndexes($tableDefinition) as $plannedIndex) {
            $this->createOne($tableDefinition, $plannedIndex['field'], $plannedIndex['unique']);
        }
    }

    /**
     * Создаёт отсутствующие индексы по имени.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createMissing(SmartTableDefinition $tableDefinition): void
    {
        $existingNames = $this->existingIndexNames($tableDefinition->getName());
        foreach ($this->plannedIndexes($tableDefinition) as $plannedIndex) {
            $indexName = self::physicalName($tableDefinition, $plannedIndex['field'], $plannedIndex['suffix']);
            if (in_array($indexName, $existingNames, true)) {
                continue;
            }

            $this->createOne($tableDefinition, $plannedIndex['field'], $plannedIndex['unique']);
        }
    }

    /**
     * Снимает managed `_idx`/`_unq`, которых карта не требует.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя планового индекса недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropLeftover(SmartTableDefinition $tableDefinition): void
    {
        $plannedNames = $this->plannedIndexNames($tableDefinition);
        $tableName = $tableDefinition->getName();
        foreach ($this->existingIndexNames($tableName) as $indexName) {
            if (
                in_array($indexName, $plannedNames, true)
                || !$this->isManagedIndexName($tableName, $indexName)
            ) {
                continue;
            }

            $this->dropOne($tableName, $indexName, str_ends_with($indexName, '_unq'));
        }
    }

    /**
     * Собирает физическое имя с суффиксом.
     *
     * @param SmartTableDefinition $tableDefinition Таблица.
     * @param BaseField $field Поле.
     * @param string $suffix idx или unq.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    private static function physicalName(
        SmartTableDefinition $tableDefinition,
        BaseField $field,
        string $suffix,
    ): string {
        $physicalName = $tableDefinition->getName() . '_' . $field->name() . '_' . $suffix;
        if (preg_match('/^[a-z][a-z0-9_]*$/', $physicalName) !== 1 || strlen($physicalName) > 64) {
            throw new MapInvalidException('Index name is invalid');
        }

        return $physicalName;
    }

    /**
     * Поля с index или unique из карты.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<int, array{field: BaseField, suffix: string, unique: bool}> Список.
     */
    private function plannedIndexes(SmartTableDefinition $tableDefinition): array
    {
        $plannedIndexes = [];
        foreach ($tableDefinition->getMap() as $field) {
            if ($field->settings()->unique()) {
                $plannedIndexes[] = ['field' => $field, 'suffix' => 'unq', 'unique' => true];
                continue;
            }

            if ($field->settings()->indexed()) {
                $plannedIndexes[] = ['field' => $field, 'suffix' => 'idx', 'unique' => false];
            }
        }

        return $plannedIndexes;
    }

    /**
     * Имена индексов, которые карта требует.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<int, string> Имена.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    private function plannedIndexNames(SmartTableDefinition $tableDefinition): array
    {
        $plannedNames = [];
        foreach ($this->plannedIndexes($tableDefinition) as $plannedIndex) {
            $plannedNames[] = self::physicalName($tableDefinition, $plannedIndex['field'], $plannedIndex['suffix']);
        }

        return $plannedNames;
    }

    /**
     * Наше одноколоночное имя `_idx` или `_unq`.
     *
     * @param string $tableName Таблица.
     * @param string $indexName Имя индекса.
     *
     * @return bool true, если имя наше.
     */
    private function isManagedIndexName(string $tableName, string $indexName): bool
    {
        $pattern = '/^' . preg_quote($tableName, '/') . '_[a-z][a-z0-9_]*_(idx|unq)$/';

        return preg_match($pattern, $indexName) === 1;
    }

    /**
     * Снимает один индекс.
     *
     * @param string $tableName Таблица.
     * @param string $indexName Имя.
     * @param bool $unique UNIQUE или INDEX.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function dropOne(string $tableName, string $indexName, bool $unique): void
    {
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        try {
            $schemaBuilder->table($tableName, function (Blueprint $blueprint) use ($indexName, $unique): void {
                if ($unique) {
                    $blueprint->dropUnique($indexName);

                    return;
                }

                $blueprint->dropIndex($indexName);
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Создаёт один индекс.
     *
     * @param SmartTableDefinition $tableDefinition Таблица.
     * @param BaseField $field Поле.
     * @param bool $unique UNIQUE или INDEX.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function createOne(SmartTableDefinition $tableDefinition, BaseField $field, bool $unique): void
    {
        $indexName = $unique
            ? self::uniqueName($tableDefinition, $field)
            : self::indexName($tableDefinition, $field);
        $tableName = $tableDefinition->getName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        try {
            $schemaBuilder->table($tableName, function (Blueprint $blueprint) use (
                $field,
                $indexName,
                $unique,
            ): void {
                if ($unique) {
                    $blueprint->unique($field->name(), $indexName);

                    return;
                }

                $blueprint->index($field->name(), $indexName);
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Имена существующих индексов таблицы.
     *
     * @param string $tableName Имя таблицы.
     *
     * @return array<int, string> Имена.
     */
    private function existingIndexNames(string $tableName): array
    {
        $indexes = $this->databaseConnection->illuminateConnection()
            ->getSchemaBuilder()
            ->getIndexes($tableName);
        $indexNames = [];
        foreach ($indexes as $index) {
            if (isset($index['name']) && is_string($index['name'])) {
                $indexNames[] = $index['name'];
            }
        }

        return $indexNames;
    }
}
