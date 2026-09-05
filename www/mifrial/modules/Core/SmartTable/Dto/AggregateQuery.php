<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Запрос aggregate: filter, group, select, limit групп.
 */
final class AggregateQuery
{
    /**
     * Создаёт запрос агрегата.
     *
     * @param FilterGroup|null $filter Дерево или нет WHERE.
     * @param array<int, string> $group Ключи GROUP BY.
     * @param array<int, string|CountField|MaxField|MinField|SumField> $select Ключи групп и меры.
     * @param int $limit Число групп 1..500.
     *
     * @return void
     *
     * @throws MapInvalidException Если group пуст или limit вне диапазона.
     */
    public function __construct(
        private readonly ?FilterGroup $filter,
        private readonly array $group,
        private readonly array $select,
        private readonly int $limit,
    ) {
        if ($this->group === [] || $this->select === []) {
            throw new MapInvalidException('Aggregate query requires group and select');
        }

        if ($this->limit < 1 || $this->limit > 500) {
            throw new MapInvalidException('Aggregate query requires limit 1..500');
        }
    }

    /**
     * Собирает запрос из массива опций.
     *
     * @param array<string, mixed> $options Опции соседа.
     *
     * @return self Запрос.
     *
     * @throws MapInvalidException Если синтаксис неверен.
     */
    public static function fromOptions(array $options): self
    {
        return (new AggregateQueryOptionsParser())->parse($options);
    }

    /**
     * Возвращает фильтр.
     *
     * @return FilterGroup|null Дерево или null.
     */
    public function filter(): ?FilterGroup
    {
        return $this->filter;
    }

    /**
     * Возвращает ключи GROUP BY.
     *
     * @return array<int, string> Свои имена.
     */
    public function group(): array
    {
        return $this->group;
    }

    /**
     * Возвращает select.
     *
     * @return array<int, string|CountField|MaxField|MinField|SumField> Ключи и меры.
     */
    public function select(): array
    {
        return $this->select;
    }

    /**
     * Возвращает limit групп.
     *
     * @return int 1..500.
     */
    public function limit(): int
    {
        return $this->limit;
    }
}
