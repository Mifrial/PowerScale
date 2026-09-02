<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\DriverErrorTranslator;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Throwable;

/**
 * Запись и чтение строк через Query Builder.
 */
final class TableRows
{
    /**
     * Создаёт доступ к строкам.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер модуля.
     * @param RowAssembler $rowAssembler Сборка payload.
     * @param DriverErrorTranslator $driverErrors Переводчик SQLSTATE.
     * @param MfvRows $mfvRows Множества полей.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly RowAssembler $rowAssembler,
        private readonly DriverErrorTranslator $driverErrors,
        private readonly MfvRows $mfvRows,
    ) {
    }

    /**
     * Вставляет строку.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<string, mixed> $values Вход API.
     *
     * @return int Новый id.
     *
     * @throws FieldInvalidException Если JSON нельзя закодировать.
     * @throws RowWriteFailedException Если insert не дал id.
     * @throws ReferenceConstraintException Если нет родителя.
     * @throws UniqueConstraintException Если unique нарушен.
     */
    public function add(SmartTableDefinition $tableDefinition, array $values): int
    {
        $payload = $this->rowAssembler->encodeJsonColumns(
            $this->rowAssembler->assembleInsert($values, $tableDefinition),
            $tableDefinition,
        );
        $multiplePayload = $this->rowAssembler->assembleMultiple($values, $tableDefinition, true);

        return $this->writeAtomic(function () use ($tableDefinition, $payload, $multiplePayload): int {
            $insertPayload = $payload === [] ? ['id' => null] : $payload;
            $insertedId = $this->driverErrors->run(function () use ($tableDefinition, $insertPayload): int {
                $this->query($tableDefinition)->insert($insertPayload);

                return (int) $this->databaseConnection->illuminateConnection()->getPdo()->lastInsertId();
            });
            if ($insertedId <= 0) {
                throw new RowWriteFailedException();
            }

            $this->replaceMultiple($tableDefinition, $insertedId, $multiplePayload);

            return $insertedId;
        });
    }

    /**
     * Обновляет строку.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     * @param array<string, mixed> $values Поля.
     *
     * @return void
     *
     * @throws RowNotFoundException Если строки нет.
     * @throws ReferenceConstraintException Если нет родителя.
     * @throws UniqueConstraintException Если unique нарушен.
     */
    public function update(SmartTableDefinition $tableDefinition, int $rowId, array $values): void
    {
        $payload = $this->rowAssembler->encodeJsonColumns(
            $this->rowAssembler->assembleUpdate($values, $tableDefinition),
            $tableDefinition,
        );
        $multiplePayload = $this->rowAssembler->assembleMultiple($values, $tableDefinition, false);
        $this->writeAtomic(function () use ($tableDefinition, $rowId, $payload, $multiplePayload): void {
            $this->assertRowExists($tableDefinition, $rowId);
            if ($payload !== []) {
                $this->driverErrors->run(function () use ($tableDefinition, $rowId, $payload): void {
                    $this->query($tableDefinition)->where('id', $rowId)->update($payload);
                });
            }

            $this->replaceMultiple($tableDefinition, $rowId, $multiplePayload);
        });
    }

    /**
     * Удаляет строку.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     *
     * @return void
     *
     * @throws RowNotFoundException Если строки нет.
     * @throws ReferenceConstraintException Если на строку есть ссылки.
     */
    public function delete(SmartTableDefinition $tableDefinition, int $rowId): void
    {
        $this->writeAtomic(function () use ($tableDefinition, $rowId): void {
            $this->assertRowExists($tableDefinition, $rowId);
            $this->mfvRows->deleteByOwner($tableDefinition, $rowId);
            $this->driverErrors->run(function () use ($tableDefinition, $rowId): void {
                $this->query($tableDefinition)->where('id', $rowId)->delete();
            });
        });
    }

    /**
     * Читает строку по id.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     *
     * @return array<string, mixed>|null Гидратированные поля.
     *
     * @throws SchemaMismatchException Если драйвер вернул не карту колонок.
     */
    public function getById(SmartTableDefinition $tableDefinition, int $rowId): ?array
    {
        $sqlColumns = $this->scalarColumnNames($tableDefinition);
        $databaseRow = $this->driverErrors->run(function () use ($tableDefinition, $sqlColumns, $rowId): mixed {
            return $this->query($tableDefinition)->select($sqlColumns)->where('id', $rowId)->first();
        });
        if ($databaseRow === null) {
            return null;
        }

        $rowMap = $this->rowMap($databaseRow);
        $this->attachMultipleToRow($rowMap, $tableDefinition, $rowId);

        return $this->rowAssembler->hydrateRow($rowMap, $tableDefinition);
    }

    /**
     * Дописывает mfv в одну строку get.
     *
     * @param array<string, mixed> $rowMap Строка драйвера.
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $ownerId Id.
     *
     * @return void
     */
    private function attachMultipleToRow(
        array &$rowMap,
        SmartTableDefinition $tableDefinition,
        int $ownerId,
    ): void {
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if (!$field->settings()->multiple()) {
                continue;
            }

            $groupedValues = $this->mfvRows->loadByOwners($tableDefinition, $field, [$ownerId]);
            $rowMap[$fieldName] = $groupedValues[$ownerId] ?? [];
        }
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
    private function assertRowExists(SmartTableDefinition $tableDefinition, int $rowId): void
    {
        $exists = $this->driverErrors->run(function () use ($tableDefinition, $rowId): bool {
            return $this->query($tableDefinition)->where('id', $rowId)->exists();
        });
        if ($exists !== true) {
            throw new RowNotFoundException();
        }
    }

    /**
     * Пишет mfv полей.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $ownerId id строки.
     * @param array<string, array<int, mixed>> $multiplePayload Поле => values.
     *
     * @return void
     */
    private function replaceMultiple(
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
     * Имена скалярных колонок.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<int, string> Колонки.
     */
    private function scalarColumnNames(SmartTableDefinition $tableDefinition): array
    {
        $columnNames = [];
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if (!$field->settings()->multiple()) {
                $columnNames[] = $fieldName;
            }
        }

        return $columnNames;
    }

    /**
     * Транзакция DML, без вложенного begin.
     *
     * @param callable(): mixed $work Работа.
     *
     * @return mixed Результат.
     *
     * @throws Throwable Любая ошибка работы.
     */
    private function writeAtomic(callable $work): mixed
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
     * Приводит строку драйвера к массиву.
     *
     * @param mixed $databaseRow Сырая строка.
     *
     * @return array<string, mixed> Карта.
     *
     * @throws SchemaMismatchException Если это не объект/массив.
     */
    private function rowMap(mixed $databaseRow): array
    {
        if (is_object($databaseRow)) {
            $databaseRow = get_object_vars($databaseRow);
        }

        if (!is_array($databaseRow)) {
            throw new SchemaMismatchException('Driver row is not a map of columns');
        }

        return $databaseRow;
    }

    /**
     * Возвращает билдер по имени таблицы.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return Builder Билдер.
     */
    private function query(SmartTableDefinition $tableDefinition): Builder
    {
        return $this->databaseConnection->illuminateConnection()->table($tableDefinition->getName());
    }
}
