<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Schema;

use Illuminate\Database\Schema\Blueprint;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\LinkSetField;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Throwable;

/**
 * FK sidecar mfv.value → id цели для linkset restrict.
 */
final class LinksetMfvForeign
{
    /**
     * Создаёт помощника FK.
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
     * Вешает или снимает FK по onDelete.
     *
     * @param SmartTableDefinition $tableDefinition Владелец.
     * @param BaseField $field Поле.
     *
     * @return void
     *
     * @throws TableMissingException Если таблицы цели нет.
     * @throws MapInvalidException Если имя constraint недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function sync(SmartTableDefinition $tableDefinition, BaseField $field): void
    {
        if (!$field instanceof LinkSetField) {
            return;
        }

        if ($field->onDelete() === 'none') {
            $this->dropIfPresent($tableDefinition, $field);

            return;
        }

        $this->addIfNeeded($tableDefinition, $field);
    }

    /**
     * Вешает FK, если constraint ещё нет.
     *
     * @param SmartTableDefinition $tableDefinition Владелец.
     * @param LinkSetField $field Поле restrict.
     *
     * @return void
     *
     * @throws TableMissingException Если таблицы цели нет.
     * @throws MapInvalidException Если имя constraint недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function addIfNeeded(SmartTableDefinition $tableDefinition, LinkSetField $field): void
    {
        $physicalName = MfvSchema::tableName($tableDefinition, $field);
        $constraintName = $this->constraintName($physicalName);
        if ($this->hasConstraint($physicalName, $constraintName)) {
            return;
        }

        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $targetName = $field->targetTableName();
        if (!$schemaBuilder->hasTable($targetName)) {
            throw new TableMissingException();
        }

        $this->createForeign($physicalName, $constraintName, $targetName, $field);
    }

    /**
     * Снимает leftover FK при onDelete none.
     *
     * @param SmartTableDefinition $tableDefinition Владелец.
     * @param LinkSetField $field Поле none.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя constraint недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function dropIfPresent(SmartTableDefinition $tableDefinition, LinkSetField $field): void
    {
        $physicalName = MfvSchema::tableName($tableDefinition, $field);
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        if (!$schemaBuilder->hasTable($physicalName)) {
            return;
        }

        $constraintName = $this->constraintName($physicalName);
        if (!$this->hasConstraint($physicalName, $constraintName)) {
            return;
        }

        try {
            $schemaBuilder->table($physicalName, function (Blueprint $blueprint) use ($constraintName): void {
                $blueprint->dropForeign($constraintName);
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Имя `{mfv}_value_fk`.
     *
     * @param string $physicalName Имя sidecar.
     *
     * @return string Имя constraint.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    private function constraintName(string $physicalName): string
    {
        $constraintName = $physicalName . '_value_fk';
        if (preg_match('/^[a-z][a-z0-9_]*$/', $constraintName) !== 1 || strlen($constraintName) > 64) {
            throw new MapInvalidException('Linkset constraint name is invalid');
        }

        return $constraintName;
    }

    /**
     * Есть ли constraint на sidecar.
     *
     * @param string $physicalName Имя sidecar.
     * @param string $constraintName Имя FK.
     *
     * @return bool true, если уже повешен.
     */
    private function hasConstraint(string $physicalName, string $constraintName): bool
    {
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        foreach ($schemaBuilder->getForeignKeys($physicalName) as $foreignKey) {
            if (isset($foreignKey['name']) && $foreignKey['name'] === $constraintName) {
                return true;
            }
        }

        return false;
    }

    /**
     * ALTER sidecar: value → id цели.
     *
     * @param string $physicalName Sidecar.
     * @param string $constraintName Имя FK.
     * @param string $targetName Таблица цели.
     * @param LinkSetField $field Поле.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function createForeign(
        string $physicalName,
        string $constraintName,
        string $targetName,
        LinkSetField $field,
    ): void {
        try {
            $this->databaseConnection->illuminateConnection()->getSchemaBuilder()->table(
                $physicalName,
                function (Blueprint $blueprint) use ($constraintName, $targetName, $field): void {
                    $blueprint->foreign('value', $constraintName)
                        ->references($field->targetIdField())
                        ->on($targetName)
                        ->restrictOnUpdate()
                        ->restrictOnDelete();
                },
            );
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }
}
