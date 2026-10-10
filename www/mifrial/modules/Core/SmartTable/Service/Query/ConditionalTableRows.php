<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Dto\ConditionalCas;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Service\DriverErrorTranslator;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Условная запись и блокирующее чтение строк карты.
 */
final class ConditionalTableRows
{
    /**
     * Создаёт conditional-доступ к строкам.
     *
     * @param RowAssembler $rowAssembler Сборка payload.
     * @param DriverErrorTranslator $driverErrors Переводчик ошибок.
     * @param TableRowOperations $rowOperations Общие операции строк.
     *
     * @return void
     */
    public function __construct(
        private readonly RowAssembler $rowAssembler,
        private readonly DriverErrorTranslator $driverErrors,
        private readonly TableRowOperations $rowOperations,
    ) {
    }

    /**
     * Пишет строку, если CAS совпал, и увеличивает счётчик на один.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     * @param ConditionalCas $cas Поле и ожидаемое значение.
     * @param array<string, mixed> $values Поля к записи без CAS.
     *
     * @return bool true, если обновлена одна строка.
     *
     * @throws MapInvalidException Если CAS-поле недопустимо.
     * @throws FieldInvalidException Если значение не прошло cast.
     * @throws FieldRequiredException Если required нарушен.
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки карты нет.
     * @throws RowWriteFailedException Если затронуто больше одной строки.
     * @throws ReferenceConstraintException Если нет родителя.
     * @throws UniqueConstraintException Если unique нарушен.
     */
    public function updateConditional(
        SmartTableDefinition $tableDefinition,
        int $rowId,
        ConditionalCas $cas,
        array $values,
    ): bool {
        $this->assertCasField($tableDefinition, $cas, $values);
        $prepared = $this->prepareConditionalUpdate($tableDefinition, $rowId, $cas, $values);

        return $this->rowOperations->writeAtomic(
            fn (): bool => $this->commitConditionalUpdate(
                $tableDefinition,
                $rowId,
                $prepared['payload'],
                $prepared['multiplePayload'],
                $prepared['filter'],
            ),
        );
    }

    /**
     * Читает строку с блокировкой в текущей транзакции.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     *
     * @return array<string, mixed>|null Гидратированные поля.
     *
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки карты нет.
     */
    public function getCurrentById(SmartTableDefinition $tableDefinition, int $rowId): ?array
    {
        $sqlColumns = $this->rowOperations->scalarColumnNames($tableDefinition);
        $databaseRow = $this->driverErrors->run(function () use ($tableDefinition, $sqlColumns, $rowId): mixed {
            return $this->rowOperations->query($tableDefinition)
                ->select($sqlColumns)
                ->where('id', $rowId)
                ->lockForUpdate()
                ->first();
        });
        if ($databaseRow === null) {
            return null;
        }

        $rowMap = $this->rowOperations->rowMap($databaseRow);
        $this->rowOperations->attachMultipleToRow($rowMap, $tableDefinition, $rowId);

        return $this->rowAssembler->hydrateRow($rowMap, $tableDefinition);
    }

    /**
     * Собирает payload и фильтр conditional update.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     * @param ConditionalCas $cas Условие.
     * @param array<string, mixed> $values Поля к записи без CAS.
     *
     * @return array<string, mixed> Подготовленные данные.
     */
    private function prepareConditionalUpdate(
        SmartTableDefinition $tableDefinition,
        int $rowId,
        ConditionalCas $cas,
        array $values,
    ): array {
        $payload = $this->rowAssembler->encodeJsonColumns(
            $this->rowAssembler->assembleUpdate($values, $tableDefinition),
            $tableDefinition,
        );
        $payload[$cas->casField] = $cas->expectedValue + 1;

        return [
            'payload' => $payload,
            'multiplePayload' => $this->rowAssembler->assembleMultiple($values, $tableDefinition, false),
            'filter' => new FilterGroup('AND', [
                new FilterCondition('id', '=', $rowId),
                new FilterCondition($cas->casField, '=', $cas->expectedValue),
            ]),
        ];
    }

    /**
     * Выполняет conditional update и sidecar-запись.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $rowId Идентификатор.
     * @param array<string, mixed> $payload Scalar payload.
     * @param array<string, array<int, mixed>> $multiplePayload Multiple payload.
     * @param FilterGroup $filter CAS-фильтр.
     *
     * @return bool true, если обновлена одна строка.
     *
     * @throws RowWriteFailedException Если затронуто больше одной строки.
     */
    private function commitConditionalUpdate(
        SmartTableDefinition $tableDefinition,
        int $rowId,
        array $payload,
        array $multiplePayload,
        FilterGroup $filter,
    ): bool {
        $affected = $this->rowOperations->updateByFilter($tableDefinition, $filter, $payload);
        if ($affected === 0) {
            return false;
        }

        if ($affected !== 1) {
            throw new RowWriteFailedException();
        }

        $this->rowOperations->replaceMultiple($tableDefinition, $rowId, $multiplePayload);

        return true;
    }

    /**
     * Проверяет, что CAS — целое scalar-поле вне payload.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param ConditionalCas $cas Условие.
     * @param array<string, mixed> $values Payload.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле нельзя использовать как CAS.
     */
    private function assertCasField(SmartTableDefinition $tableDefinition, ConditionalCas $cas, array $values): void
    {
        if (array_key_exists($cas->casField, $values)) {
            throw new MapInvalidException('CAS field must not be in values');
        }

        $field = $tableDefinition->getMap()[$cas->casField] ?? null;
        if (!$field instanceof IntField || $field->isMfv()) {
            throw new MapInvalidException('CAS field must be a scalar integer');
        }
    }
}
