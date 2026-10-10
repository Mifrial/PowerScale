<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Service\DriverErrorTranslator;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Запись и чтение строк через Query Builder.
 */
final class TableRows
{
    /**
     * Создаёт доступ к строкам.
     *
     * @param RowAssembler $rowAssembler Сборка payload.
     * @param DriverErrorTranslator $driverErrors Переводчик SQLSTATE.
     * @param MfvRows $mfvRows Множества полей.
     * @param TableRowOperations $rowOperations Общие операции строк.
     * @param ConditionalTableRows $conditionalTableRows Условные строки.
     *
     * @return void
     */
    public function __construct(
        private readonly RowAssembler $rowAssembler,
        private readonly DriverErrorTranslator $driverErrors,
        private readonly MfvRows $mfvRows,
        private readonly TableRowOperations $rowOperations,
        private readonly ConditionalTableRows $conditionalTableRows,
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

        return $this->rowOperations->writeAtomic(function () use ($tableDefinition, $payload, $multiplePayload): int {
            $insertPayload = $payload === [] ? ['id' => null] : $payload;
            $insertedId = $this->driverErrors->run(function () use ($tableDefinition, $insertPayload): int {
                $this->rowOperations->query($tableDefinition)->insert($insertPayload);

                return $this->rowOperations->lastInsertId();
            });
            if ($insertedId <= 0) {
                throw new RowWriteFailedException();
            }

            $this->rowOperations->replaceMultiple($tableDefinition, $insertedId, $multiplePayload);

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
        $this->rowOperations->writeAtomic(function () use ($tableDefinition, $rowId, $payload, $multiplePayload): void {
            $this->rowOperations->assertRowExists($tableDefinition, $rowId);
            if ($payload !== []) {
                $this->driverErrors->run(function () use ($tableDefinition, $rowId, $payload): void {
                    $this->rowOperations->query($tableDefinition)->where('id', $rowId)->update($payload);
                });
            }

            $this->rowOperations->replaceMultiple($tableDefinition, $rowId, $multiplePayload);
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
        $this->rowOperations->writeAtomic(function () use ($tableDefinition, $rowId): void {
            $this->rowOperations->assertRowExists($tableDefinition, $rowId);
            $this->mfvRows->deleteByOwner($tableDefinition, $rowId);
            $this->driverErrors->run(function () use ($tableDefinition, $rowId): void {
                $this->rowOperations->query($tableDefinition)->where('id', $rowId)->delete();
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
        $sqlColumns = $this->rowOperations->scalarColumnNames($tableDefinition);
        $databaseRow = $this->driverErrors->run(function () use ($tableDefinition, $sqlColumns, $rowId): mixed {
            return $this->rowOperations->query($tableDefinition)->select($sqlColumns)->where('id', $rowId)->first();
        });
        if ($databaseRow === null) {
            return null;
        }

        $rowMap = $this->rowOperations->rowMap($databaseRow);
        $this->rowOperations->attachMultipleToRow($rowMap, $tableDefinition, $rowId);

        return $this->rowAssembler->hydrateRow($rowMap, $tableDefinition);
    }

    /**
     * Вставляет пачку строк и возвращает id в порядке входа.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, mixed> $rows Список карт.
     *
     * @return array<int, int> Новые id.
     *
     * @throws MapInvalidException Если пачка некорректна.
     * @throws RowWriteFailedException Если insert не дал id.
     * @throws ReferenceConstraintException Если нет родителя.
     * @throws UniqueConstraintException Если unique нарушен.
     */
    public function addMany(SmartTableDefinition $tableDefinition, array $rows): array
    {
        (new InsertBatch())->assertRows($rows);
        $insertPayloads = [];
        $multiplePayloads = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new MapInvalidException('addMany row must be a map');
            }

            $insertPayloads[] = $this->rowAssembler->encodeJsonColumns(
                $this->rowAssembler->assembleInsert($row, $tableDefinition),
                $tableDefinition,
            );
            $multiplePayloads[] = $this->rowAssembler->assembleMultiple($row, $tableDefinition, true);
        }

        return $this->rowOperations->writeAtomic(
            function () use ($tableDefinition, $insertPayloads, $multiplePayloads): array {
                return $this->insertBatchRows($tableDefinition, $insertPayloads, $multiplePayloads);
            },
        );
    }

    /**
     * Возвращает условные операции этой карты.
     *
     * @return ConditionalTableRows Условные строки.
     */
    public function getConditionalRows(): ConditionalTableRows
    {
        return $this->conditionalTableRows;
    }

    /**
     * Multi-insert и sidecar по выданным id.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, array<string, mixed>> $insertPayloads Скаляры рядов.
     * @param array<int, array<string, array<int, mixed>>> $multiplePayloads mfv рядов.
     *
     * @return array<int, int> Новые id.
     *
     * @throws RowWriteFailedException Если insert не дал id.
     */
    private function insertBatchRows(
        SmartTableDefinition $tableDefinition,
        array $insertPayloads,
        array $multiplePayloads,
    ): array {
        $normalizedRows = [];
        foreach ($insertPayloads as $payload) {
            $normalizedRows[] = $payload === [] ? ['id' => null] : $payload;
        }

        $firstId = $this->driverErrors->run(function () use ($tableDefinition, $normalizedRows): int {
            $this->rowOperations->query($tableDefinition)->insert($normalizedRows);

            return $this->rowOperations->lastInsertId();
        });
        if ($firstId <= 0) {
            throw new RowWriteFailedException();
        }

        $rowIds = range($firstId, $firstId + count($normalizedRows) - 1);
        foreach ($rowIds as $rowIndex => $rowId) {
            $this->rowOperations->replaceMultiple($tableDefinition, $rowId, $multiplePayloads[$rowIndex]);
        }

        return $rowIds;
    }
}
