<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Запрос getList: filter, sort, page, select.
 */
final class ListQuery
{
    /**
     * Создаёт запрос.
     *
     * @param FilterGroup|null $filter Дерево или нет WHERE.
     * @param array<string, string> $sort Поле => ASC|DESC.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param bool $countTotal Считать COUNT.
     * @param array<int, string>|null $select Поля или вся карта.
     *
     * @return void
     *
     * @throws MapInvalidException Если limit или offset вне диапазона.
     */
    public function __construct(
        private readonly ?FilterGroup $filter,
        private readonly array $sort,
        private readonly int $limit,
        private readonly int $offset,
        private readonly bool $countTotal,
        private readonly ?array $select,
    ) {
        if ($limit < 1 || $limit > 500) {
            throw new MapInvalidException('List query requires limit 1..500');
        }

        if ($offset < 0) {
            throw new MapInvalidException('List query offset is invalid');
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
        return (new ListQueryOptionsParser())->parse($options);
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
     * Возвращает сортировку.
     *
     * @return array<string, string> Поле => ASC|DESC.
     */
    public function sort(): array
    {
        return $this->sort;
    }

    /**
     * Возвращает limit.
     *
     * @return int 1..500.
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Возвращает offset.
     *
     * @return int Сдвиг ≥ 0.
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Нужен ли COUNT.
     *
     * @return bool true, если считать total.
     */
    public function countTotal(): bool
    {
        return $this->countTotal;
    }

    /**
     * Возвращает select.
     *
     * @return array<int, string>|null Имена или null (вся карта).
     */
    public function select(): ?array
    {
        return $this->select;
    }
}
