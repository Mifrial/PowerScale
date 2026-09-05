<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Cache;

use JsonException;
use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Dto\MinField;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Dto\SumField;
use Mifrial\Core\SmartTable\Exception\Cache\CacheConfigInvalidException;

/**
 * Стабильный ключ list/aggregate: стол плюс канон запроса.
 */
final class ListCacheKey
{
    /**
     * Собирает ключ списка.
     *
     * @param string $tableName Физическое имя.
     * @param ListQuery $listQuery Запрос.
     *
     * @return string Ключ.
     *
     * @throws CacheConfigInvalidException Если json не собрался.
     */
    public function make(string $tableName, ListQuery $listQuery): string
    {
        return $this->encode([
            'kind' => 'list',
            'table' => $tableName,
            'filter' => $this->filterTree($listQuery->filter()),
            'sort' => $listQuery->sort(),
            'limit' => $listQuery->limit(),
            'offset' => $listQuery->offset(),
            'countTotal' => $listQuery->countTotal(),
            'select' => $this->canonValue($listQuery->select()),
        ]);
    }

    /**
     * Собирает ключ агрегата.
     *
     * @param string $tableName Физическое имя.
     * @param AggregateQuery $aggregateQuery Запрос.
     *
     * @return string Ключ.
     *
     * @throws CacheConfigInvalidException Если json не собрался.
     */
    public function makeAggregate(string $tableName, AggregateQuery $aggregateQuery): string
    {
        return $this->encode([
            'kind' => 'aggregate',
            'table' => $tableName,
            'filter' => $this->filterTree($aggregateQuery->filter()),
            'group' => $aggregateQuery->group(),
            'select' => $this->canonValue($aggregateQuery->select()),
            'limit' => $aggregateQuery->limit(),
        ]);
    }

    /**
     * Разворачивает фильтр в массив для ключа.
     *
     * @param FilterGroup|null $filterGroup Дерево.
     *
     * @return array<string, mixed>|null Узел.
     */
    private function filterTree(?FilterGroup $filterGroup): ?array
    {
        if ($filterGroup === null) {
            return null;
        }

        $children = [];
        foreach ($filterGroup->children() as $child) {
            if ($child instanceof FilterGroup) {
                $children[] = $this->filterTree($child);
                continue;
            }

            if ($child instanceof FilterCondition) {
                $children[] = [
                    'field' => $child->fieldName(),
                    'operator' => $child->operator(),
                    'operand' => $this->canonValue($child->operand()),
                ];
            }
        }

        return ['logic' => $filterGroup->logic(), 'children' => $children];
    }

    /**
     * Канон значения ключа: DateTime unix, меры, подзапрос.
     *
     * @param mixed $value Операнд или select.
     *
     * @return mixed JSON-совместимое.
     */
    private function canonValue(mixed $value): mixed
    {
        $objectCanon = $this->canonObject($value);
        if ($objectCanon !== null) {
            return $objectCanon;
        }

        if (!is_array($value)) {
            return $value;
        }

        $canon = [];
        foreach ($value as $itemKey => $itemValue) {
            $canon[$itemKey] = $this->canonValue($itemValue);
        }

        return $canon;
    }

    /**
     * Канон известного объекта или null.
     *
     * @param mixed $value Кандидат.
     *
     * @return array<string, mixed>|null Канон.
     */
    private function canonObject(mixed $value): ?array
    {
        if ($value instanceof UnixDateTime) {
            return ['dt' => $value->toUnix()];
        }

        if ($value instanceof OuterColumn) {
            return ['outer' => $value->fieldName()];
        }

        return $this->canonMeasure($value) ?? $this->canonSubquery($value);
    }

    /**
     * Канон подзапроса в фильтре.
     *
     * @param mixed $value Кандидат.
     *
     * @return array<string, mixed>|null Канон.
     */
    private function canonSubquery(mixed $value): ?array
    {
        if (!$value instanceof SubqueryValue) {
            return null;
        }

        return [
            'subquery' => $value->table(),
            'field' => $value->field(),
            'filter' => $this->filterTree($value->filter()),
            'coalesce' => $this->canonValue($value->coalesce()),
        ];
    }

    /**
     * Канон меры select.
     *
     * @param mixed $value Кандидат.
     *
     * @return array<string, mixed>|null Канон.
     */
    private function canonMeasure(mixed $value): ?array
    {
        if ($value instanceof CountField) {
            return ['count' => $value->alias()];
        }

        if ($value instanceof MaxField) {
            $kind = 'max';
        } elseif ($value instanceof MinField) {
            $kind = 'min';
        } elseif ($value instanceof SumField) {
            $kind = 'sum';
        } else {
            return null;
        }

        return [$kind => $value->fieldName(), 'alias' => $value->alias()];
    }

    /**
     * JSON ключа.
     *
     * @param array<string, mixed> $payload Канон.
     *
     * @return string Ключ.
     *
     * @throws CacheConfigInvalidException Если json не собрался.
     */
    private function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new CacheConfigInvalidException('Cache list key is invalid');
        }
    }
}
