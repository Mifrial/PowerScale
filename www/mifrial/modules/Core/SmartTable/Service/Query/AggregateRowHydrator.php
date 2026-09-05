<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Support\Collection;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Dto\MinField;
use Mifrial\Core\SmartTable\Dto\SumField;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Гидратация рядов GROUP BY в ключи select.
 */
final class AggregateRowHydrator
{
    /**
     * Гидратирует ряды драйвера.
     *
     * @param Collection $databaseRows Сырые ряды.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param AggregateQuery $aggregateQuery Запрос.
     *
     * @return array<int, array<string, mixed>> Ряды API.
     */
    public function hydrateRows(
        Collection $databaseRows,
        SmartTableDefinition $tableDefinition,
        AggregateQuery $aggregateQuery,
    ): array {
        $hydratedRows = [];
        foreach ($databaseRows as $databaseRow) {
            $hydratedRows[] = $this->hydrateRow($this->rowMap($databaseRow), $tableDefinition, $aggregateQuery);
        }

        return $hydratedRows;
    }

    /**
     * Гидратация одного ряда в порядке select.
     *
     * @param array<string, mixed> $rowMap Колонки драйвера.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param AggregateQuery $aggregateQuery Запрос.
     *
     * @return array<string, mixed> Ряд API.
     */
    private function hydrateRow(
        array $rowMap,
        SmartTableDefinition $tableDefinition,
        AggregateQuery $aggregateQuery,
    ): array {
        $hydratedRow = [];
        foreach ($aggregateQuery->select() as $selectItem) {
            if (is_string($selectItem)) {
                $hydratedRow[$selectItem] = $tableDefinition->getMap()[$selectItem]->hydrate(
                    $rowMap[$selectItem] ?? null,
                );
                continue;
            }

            $hydratedRow[$selectItem->alias()] = $this->hydrateMeasure(
                $rowMap[$selectItem->alias()] ?? null,
                $tableDefinition,
                $selectItem,
            );
        }

        return $hydratedRow;
    }

    /**
     * Гидратация значения меры.
     *
     * @param mixed $databaseValue Сырое.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param CountField|MaxField|MinField|SumField $measure Мера.
     *
     * @return mixed PHP-значение.
     *
     * @throws MapInvalidException Если SUM не целое.
     */
    private function hydrateMeasure(
        mixed $databaseValue,
        SmartTableDefinition $tableDefinition,
        CountField|MaxField|MinField|SumField $measure,
    ): mixed {
        if ($measure instanceof CountField) {
            return (int) $databaseValue;
        }

        if ($measure instanceof SumField) {
            return $this->hydrateSum($databaseValue);
        }

        return $tableDefinition->getMap()[$measure->fieldName()]->hydrate($databaseValue);
    }

    /**
     * SUM после CAST: int или null.
     *
     * @param mixed $databaseValue Сырое.
     *
     * @return int|null Целое.
     *
     * @throws MapInvalidException Если значение не целое.
     */
    private function hydrateSum(mixed $databaseValue): ?int
    {
        if ($databaseValue === null) {
            return null;
        }

        if (is_int($databaseValue)) {
            return $databaseValue;
        }

        if (is_string($databaseValue) && preg_match('/^-?\d+$/', $databaseValue) === 1) {
            return (int) $databaseValue;
        }

        throw new MapInvalidException('Aggregate sum is invalid');
    }

    /**
     * Приводит строку драйвера к массиву колонок.
     *
     * @param mixed $databaseRow Сырая строка.
     *
     * @return array<string, mixed> Карта колонок.
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
}
