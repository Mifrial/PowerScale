<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Illuminate\Database\Schema\Blueprint;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Throwable;

/**
 * DDL внешних ключей reference-полей.
 */
final class ReferenceSchema
{
    /**
     * Создаёт DDL FK.
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
     * Имя constraint `{table}_{field}_fk`.
     *
     * @param SmartTableDefinition $tableDefinition Таблица-источник.
     * @param ReferenceField $field Поле.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    public static function constraintName(SmartTableDefinition $tableDefinition, ReferenceField $field): string
    {
        $constraintName = $tableDefinition->getName() . '_' . $field->name() . '_fk';
        if (preg_match('/^[a-z][a-z0-9_]*$/', $constraintName) !== 1 || strlen($constraintName) > 64) {
            throw new MapInvalidException('Reference constraint name is invalid');
        }

        return $constraintName;
    }

    /**
     * Проверяет, что таблицы целей FK уже есть.
     *
     * Self-reference до CREATE своей таблицы пропускается.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableMissingException Если таблицы цели нет.
     */
    public function assertTargetsExist(SmartTableDefinition $tableDefinition): void
    {
        $tableName = $tableDefinition->getName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $ownTableExists = $schemaBuilder->hasTable($tableName);
        foreach ($this->foreignFields($tableDefinition) as $field) {
            $targetName = $field->targetTableName();
            if ($targetName === $tableName && !$ownTableExists) {
                continue;
            }

            if (!$schemaBuilder->hasTable($targetName)) {
                throw new TableMissingException();
            }
        }
    }

    /**
     * Создаёт все FK карты с onDelete не none.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableMissingException Если таблицы цели нет.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createAll(SmartTableDefinition $tableDefinition): void
    {
        foreach ($this->foreignFields($tableDefinition) as $field) {
            $this->createOne($tableDefinition, $field);
        }
    }

    /**
     * Создаёт отсутствующие FK.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws TableMissingException Если таблицы цели нет.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createMissing(SmartTableDefinition $tableDefinition): void
    {
        $existingNames = $this->existingConstraintNames($tableDefinition->getName());
        foreach ($this->foreignFields($tableDefinition) as $field) {
            $constraintName = self::constraintName($tableDefinition, $field);
            if (in_array($constraintName, $existingNames, true)) {
                continue;
            }

            $this->createOne($tableDefinition, $field);
        }
    }

    /**
     * Снимает managed `_fk`, которых карта не требует.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя constraint недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropLeftover(SmartTableDefinition $tableDefinition): void
    {
        $keepNames = [];
        foreach ($this->foreignFields($tableDefinition) as $field) {
            $keepNames[] = self::constraintName($tableDefinition, $field);
        }

        $this->dropManagedExcept($tableDefinition->getName(), $keepNames);
    }

    /**
     * Снимает все наши `_fk` этой таблицы, включая leftover.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropOwned(SmartTableDefinition $tableDefinition): void
    {
        $this->dropManagedExcept($tableDefinition->getName(), []);
    }

    /**
     * Поля с физическим FK.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<int, ReferenceField> Поля.
     */
    private function foreignFields(SmartTableDefinition $tableDefinition): array
    {
        $foreignFields = [];
        foreach ($tableDefinition->getMap() as $field) {
            if ($field instanceof ReferenceField && $field->onDelete() !== 'none') {
                $foreignFields[] = $field;
            }
        }

        return $foreignFields;
    }

    /**
     * Снимает наши `_fk`, кроме переданных имён.
     *
     * @param string $tableName Таблица.
     * @param array<int, string> $keepNames Имена, которые оставить.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function dropManagedExcept(string $tableName, array $keepNames): void
    {
        $pattern = '/^' . preg_quote($tableName, '/') . '_[a-z][a-z0-9_]*_fk$/';
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        foreach ($this->existingConstraintNames($tableName) as $constraintName) {
            if (
                in_array($constraintName, $keepNames, true)
                || preg_match($pattern, $constraintName) !== 1
            ) {
                continue;
            }

            try {
                $schemaBuilder->table($tableName, function (Blueprint $blueprint) use ($constraintName): void {
                    $blueprint->dropForeign($constraintName);
                });
            } catch (SmartTableException $exception) {
                throw $exception;
            } catch (Throwable $throwable) {
                throw new DdlFailedException($throwable);
            }
        }
    }

    /**
     * Создаёт один FK.
     *
     * @param SmartTableDefinition $tableDefinition Источник.
     * @param ReferenceField $field Поле.
     *
     * @return void
     *
     * @throws TableMissingException Если цели нет.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function createOne(SmartTableDefinition $tableDefinition, ReferenceField $field): void
    {
        $targetName = $field->targetTableName();
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        if (!$schemaBuilder->hasTable($targetName)) {
            throw new TableMissingException();
        }

        $constraintName = self::constraintName($tableDefinition, $field);
        try {
            $schemaBuilder->table(
                $tableDefinition->getName(),
                function (Blueprint $blueprint) use ($field, $constraintName, $targetName): void {
                    $this->defineForeign($blueprint, $field, $constraintName, $targetName);
                },
            );
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Вешает FK restrict/setNull без CASCADE.
     *
     * @param Blueprint $blueprint ALTER.
     * @param ReferenceField $field Поле.
     * @param string $constraintName Имя constraint.
     * @param string $targetName Таблица цели.
     *
     * @return void
     */
    private function defineForeign(
        Blueprint $blueprint,
        ReferenceField $field,
        string $constraintName,
        string $targetName,
    ): void {
        $foreignKey = $blueprint->foreign($field->name(), $constraintName)
            ->references('id')
            ->on($targetName)
            ->restrictOnUpdate();
        if ($field->onDelete() === 'setNull') {
            $foreignKey->nullOnDelete();

            return;
        }

        $foreignKey->restrictOnDelete();
    }

    /**
     * Имена существующих FK таблицы.
     *
     * @param string $tableName Имя таблицы.
     *
     * @return array<int, string> Имена.
     */
    private function existingConstraintNames(string $tableName): array
    {
        $foreignKeys = $this->databaseConnection->illuminateConnection()
            ->getSchemaBuilder()
            ->getForeignKeys($tableName);
        $constraintNames = [];
        foreach ($foreignKeys as $foreignKey) {
            if (isset($foreignKey['name']) && is_string($foreignKey['name'])) {
                $constraintNames[] = $foreignKey['name'];
            }
        }

        return $constraintNames;
    }
}
