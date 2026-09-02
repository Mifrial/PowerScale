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
 * DDL таблиц {table}_mfv_{field}.
 */
final class MfvSchema
{
    /**
     * Создаёт DDL sidecar.
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
     * Физическое имя mfv-таблицы.
     *
     * @param SmartTableDefinition $tableDefinition Основная таблица.
     * @param BaseField $field Multiple-поле.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    public static function tableName(SmartTableDefinition $tableDefinition, BaseField $field): string
    {
        $physicalName = self::physicalNameOrNull($tableDefinition, $field);
        if ($physicalName === null) {
            throw new MapInvalidException('Multiple storage table name is invalid');
        }

        return $physicalName;
    }

    /**
     * Создаёт все mfv-таблицы карты.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createAll(SmartTableDefinition $tableDefinition): void
    {
        foreach ($this->multipleFields($tableDefinition) as $field) {
            $this->createOne($tableDefinition, $field);
        }
    }

    /**
     * Создаёт отсутствующие mfv-таблицы.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createMissing(SmartTableDefinition $tableDefinition): void
    {
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        foreach ($this->multipleFields($tableDefinition) as $field) {
            $physicalName = self::tableName($tableDefinition, $field);
            if ($schemaBuilder->hasTable($physicalName)) {
                continue;
            }

            $this->createOne($tableDefinition, $field);
        }
    }

    /**
     * Снимает sidecar полей карты, у которых multiple выключен.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws MapInvalidException Если карта некорректна.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropLeftover(SmartTableDefinition $tableDefinition): void
    {
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if ($fieldName === 'id' || $field->settings()->multiple()) {
                continue;
            }

            $physicalName = self::physicalNameOrNull($tableDefinition, $field);
            if ($physicalName === null) {
                continue;
            }

            $this->dropIfExists($physicalName);
        }
    }

    /**
     * Снимает sidecar полей карты с multiple.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя sidecar недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropMapped(SmartTableDefinition $tableDefinition): void
    {
        foreach ($this->multipleFields($tableDefinition) as $field) {
            $this->dropIfExists(self::tableName($tableDefinition, $field));
        }
    }

    /**
     * Снимает sidecar одного multiple-поля, если таблица есть.
     *
     * @param SmartTableDefinition $tableDefinition Основная таблица.
     * @param BaseField $field Поле карты.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя sidecar недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropFieldStorage(SmartTableDefinition $tableDefinition, BaseField $field): void
    {
        $this->dropIfExists(self::tableName($tableDefinition, $field));
    }

    /**
     * Допустимое имя sidecar или null, если шаблон не сходится.
     *
     * @param SmartTableDefinition $tableDefinition Основная таблица.
     * @param BaseField $field Поле.
     *
     * @return string|null Имя или null.
     */
    private static function physicalNameOrNull(SmartTableDefinition $tableDefinition, BaseField $field): ?string
    {
        $physicalName = $tableDefinition->getName() . '_mfv_' . $field->name();
        if (preg_match('/^[a-z][a-z0-9_]*$/', $physicalName) !== 1 || strlen($physicalName) > 64) {
            return null;
        }

        return $physicalName;
    }

    /**
     * Удаляет sidecar, если таблица есть.
     *
     * @param string $physicalName Имя.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function dropIfExists(string $physicalName): void
    {
        try {
            $this->databaseConnection->illuminateConnection()->getSchemaBuilder()->dropIfExists($physicalName);
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Создаёт одну mfv-таблицу.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param BaseField $field Поле.
     *
     * @return void
     *
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    private function createOne(SmartTableDefinition $tableDefinition, BaseField $field): void
    {
        $physicalName = self::tableName($tableDefinition, $field);
        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        try {
            $schemaBuilder->create($physicalName, function (Blueprint $blueprint) use ($field, $tableDefinition): void {
                $this->defineOwnerId($blueprint, $tableDefinition);
                $valueColumn = $this->valueColumn($blueprint, $field->column());
                $valueColumn->nullable(false);
                $blueprint->primary(['owner_id', 'value']);
            });
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DdlFailedException($throwable);
        }
    }

    /**
     * Multiple-поля карты.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<int, BaseField> Поля.
     */
    private function multipleFields(SmartTableDefinition $tableDefinition): array
    {
        $multipleFields = [];
        foreach ($tableDefinition->getMap() as $field) {
            if ($field->settings()->multiple()) {
                $multipleFields[] = $field;
            }
        }

        return $multipleFields;
    }

    /**
     * Колонка value по ColumnMeta.
     *
     * @param Blueprint $blueprint Чертёж.
     * @param ColumnMeta $columnMeta Метаданные элемента.
     *
     * @return Fluent Колонка.
     *
     * @throws MapInvalidException Если тип неизвестен.
     */
    private function valueColumn(Blueprint $blueprint, ColumnMeta $columnMeta): Fluent
    {
        return match ($columnMeta->sqlType) {
            'VARCHAR' => $this->varcharValue($blueprint, $columnMeta),
            'INT' => $blueprint->integer('value'),
            'BIGINT' => $blueprint->bigInteger('value'),
            'TINYINT' => $this->tinyIntegerValue($blueprint, $columnMeta),
            default => throw new MapInvalidException('Unknown column sqlType'),
        };
    }

    /**
     * Создаёт колонку VARCHAR.
     *
     * @param Blueprint $blueprint Чертёж.
     * @param ColumnMeta $columnMeta Метаданные.
     *
     * @return Fluent Колонка.
     *
     * @throws MapInvalidException Если длины нет.
     */
    private function varcharValue(Blueprint $blueprint, ColumnMeta $columnMeta): Fluent
    {
        if ($columnMeta->length === null || $columnMeta->length < 1) {
            throw new MapInvalidException('VARCHAR requires length');
        }

        return $blueprint->string('value', $columnMeta->length);
    }

    /**
     * Создаёт колонку TINYINT.
     *
     * @param Blueprint $blueprint Чертёж.
     * @param ColumnMeta $columnMeta Метаданные.
     *
     * @return Fluent Колонка.
     *
     * @throws MapInvalidException Если длина не 1.
     */
    private function tinyIntegerValue(Blueprint $blueprint, ColumnMeta $columnMeta): Fluent
    {
        if ($columnMeta->length !== 1) {
            throw new MapInvalidException('TINYINT requires length 1');
        }

        return $blueprint->tinyInteger('value');
    }

    /**
     * Колонка owner_id той же ширины, что id таблицы.
     *
     * @param Blueprint $blueprint Чертёж sidecar.
     * @param SmartTableDefinition $tableDefinition Карта владельца.
     *
     * @return void
     */
    private function defineOwnerId(Blueprint $blueprint, SmartTableDefinition $tableDefinition): void
    {
        $idField = $tableDefinition->getMap()['id'] ?? null;
        if ($idField instanceof IdField && $idField->isBig()) {
            $blueprint->bigInteger('owner_id');

            return;
        }

        $blueprint->integer('owner_id');
    }
}
