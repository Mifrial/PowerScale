<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\DriverErrorTranslator;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Throwable;

/**
 * Общие низкоуровневые операции строковой карты.
 */
final class TableRowOperations
{
    /**
     * Создаёт операции карты.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Соединение.
     * @param DriverErrorTranslator $driverErrors Переводчик ошибок.
     * @param MfvRows $mfvRows Операции multiple-полей.
     * @param ListQueryCompiler $listQueryCompiler Компилятор фильтров.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly DriverErrorTranslator $driverErrors,
        private readonly MfvRows $mfvRows,
        private readonly ListQueryCompiler $listQueryCompiler,
    ) {
    }

    /**
     * Выполняет работу в локальной или внешней транзакции.
     *
     * @param callable(): mixed $work Работа.
     *
     * @return mixed Результат работы.
     *
     * @throws Throwable Любая ошибка работы.
     */
    public function writeAtomic(callable $work): mixed
    {
        $connection = $this->databaseConnection->illuminateConnection();
        $ownsTransaction = $connection->transactionLevel() === 0;
        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $result = $work();
            if ($ownsTransaction) {
                $connection->commit();
            }

            return $result;
        } catch (Throwable $throwable) {
            if ($ownsTransaction && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * Возвращает билдер по имени таблицы.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return Builder Билдер.
     */
    public function query(SmartTableDefinition $tableDefinition): Builder
    {
        return $this->databaseConnection->illuminateConnection()->table($tableDefinition->getName());
    }

    /**
     * Возвращает последний вставленный id.
     *
     * @return int Id строки.
     */
    public function lastInsertId(): int
    {
        return (int) $this->databaseConnection->illuminateConnection()->getPdo()->lastInsertId();
    }

    /**
     * Обновляет scalar-колонки по непустому фильтру своей карты.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param FilterGroup $filter Условие.
     * @param array<string, mixed> $assembledScalarValues Подготовленный payload.
     *
     * @return int Число затронутых строк.
     *
     * @throws MapInvalidException Если фильтр пуст.
     */
    public function updateByFilter(
        SmartTableDefinition $tableDefinition,
        FilterGroup $filter,
        array $assembledScalarValues,
    ): int {
        if ($filter->children() === []) {
            throw new MapInvalidException('Update filter is empty');
        }

        return $this->driverErrors->run(function () use ($tableDefinition, $filter, $assembledScalarValues): int {
            $query = $this->query($tableDefinition);
            $this->listQueryCompiler->applyFilter($query, $filter, $tableDefinition);

            return $query->update($assembledScalarValues);
        });
    }

    /**
     * Проверяет наличие строки.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     *
     * @return void
     *
     * @throws RowNotFoundException Если строки нет.
     */
    public function assertRowExists(SmartTableDefinition $tableDefinition, int $rowId): void
    {
        $exists = $this->driverErrors->run(function () use ($tableDefinition, $rowId): bool {
            return $this->query($tableDefinition)->where('id', $rowId)->exists();
        });
        if ($exists !== true) {
            throw new RowNotFoundException();
        }
    }

    /**
     * Пишет mfv-поля.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $ownerId Id строки.
     * @param array<string, array<int, mixed>> $multiplePayload Поле → значения.
     *
     * @return void
     */
    public function replaceMultiple(
        SmartTableDefinition $tableDefinition,
        int $ownerId,
        array $multiplePayload,
    ): void {
        $fieldMap = $tableDefinition->getMap();
        foreach ($multiplePayload as $fieldName => $extractedValues) {
            $this->mfvRows->replace($tableDefinition, $fieldMap[$fieldName], $ownerId, $extractedValues);
        }
    }

    /**
     * Дописывает mfv-поля в строку.
     *
     * @param array<string, mixed> $rowMap Строка драйвера.
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $ownerId Id строки.
     *
     * @return void
     */
    public function attachMultipleToRow(
        array &$rowMap,
        SmartTableDefinition $tableDefinition,
        int $ownerId,
    ): void {
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if (!$field->isMfv()) {
                continue;
            }

            $groupedValues = $this->mfvRows->loadByOwners($tableDefinition, $field, [$ownerId]);
            $rowMap[$fieldName] = $groupedValues[$ownerId] ?? [];
        }
    }

    /**
     * Возвращает имена scalar-колонок.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<int, string> Колонки.
     */
    public function scalarColumnNames(SmartTableDefinition $tableDefinition): array
    {
        $columnNames = [];
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if (!$field->isMfv()) {
                $columnNames[] = $fieldName;
            }
        }

        return $columnNames;
    }

    /**
     * Приводит строку драйвера к массиву.
     *
     * @param mixed $databaseRow Сырая строка.
     *
     * @return array<string, mixed> Карта.
     *
     * @throws SchemaMismatchException Если это не объект/массив.
     */
    public function rowMap(mixed $databaseRow): array
    {
        if (is_object($databaseRow)) {
            $databaseRow = get_object_vars($databaseRow);
        }

        if (!is_array($databaseRow)) {
            throw new SchemaMismatchException('Driver row is not a map of columns');
        }

        return $databaseRow;
    }
}
