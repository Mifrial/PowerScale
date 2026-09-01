<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Синтаксический разбор опций getList без карты таблицы.
 */
final class ListQueryOptionsParser
{
    /**
     * @var array<int, string>
     */
    private const OPTION_KEYS = ['filter', 'sort', 'limit', 'offset', 'countTotal', 'select'];

    /**
     * Собирает ListQuery из массива.
     *
     * @param array<string, mixed> $options Опции соседа.
     *
     * @return ListQuery Запрос.
     *
     * @throws MapInvalidException Если синтаксис неверен.
     */
    public function parse(array $options): ListQuery
    {
        $this->assertKnownOptionKeys($options);

        return new ListQuery(
            $this->parseFilter($options['filter'] ?? null),
            $this->parseSort($options['sort'] ?? []),
            $this->parseLimit($options),
            $this->parseOffset($options['offset'] ?? 0),
            $this->parseCountTotal($options),
            $this->parseSelect($options),
        );
    }

    /**
     * Проверяет ключи верхнего уровня.
     *
     * @param array<string, mixed> $options Опции.
     *
     * @return void
     *
     * @throws MapInvalidException Если ключ неизвестен.
     */
    private function assertKnownOptionKeys(array $options): void
    {
        foreach (array_keys($options) as $optionKey) {
            if (!is_string($optionKey) || !in_array($optionKey, self::OPTION_KEYS, true)) {
                throw new MapInvalidException('Unknown list query option');
            }
        }
    }

    /**
     * Разбирает filter.
     *
     * @param mixed $filterValue Сырой фильтр.
     *
     * @return FilterGroup|null Дерево.
     *
     * @throws MapInvalidException Если фильтр не массив.
     */
    private function parseFilter(mixed $filterValue): ?FilterGroup
    {
        if ($filterValue === null || $filterValue === []) {
            return null;
        }

        if (!is_array($filterValue)) {
            throw new MapInvalidException('Filter must be an array');
        }

        return (new FilterTreeParser())->parseGroup($filterValue);
    }

    /**
     * Разбирает sort.
     *
     * @param mixed $sortValue Сырой sort.
     *
     * @return array<string, string> Нормализованный sort.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    private function parseSort(mixed $sortValue): array
    {
        if (!is_array($sortValue)) {
            throw new MapInvalidException('Sort must be an array');
        }

        $normalizedSort = [];
        foreach ($sortValue as $fieldName => $direction) {
            $this->assertFieldName($fieldName, 'Sort field name is invalid');
            $normalizedSort[$fieldName] = $this->parseSortDirection($direction);
        }

        return $normalizedSort;
    }

    /**
     * Нормализует направление сортировки.
     *
     * @param mixed $direction Сырое направление.
     *
     * @return string ASC или DESC.
     *
     * @throws MapInvalidException Если направление неверно.
     */
    private function parseSortDirection(mixed $direction): string
    {
        if (!is_string($direction)) {
            throw new MapInvalidException('Sort direction is invalid');
        }

        $normalizedDirection = strtoupper($direction);
        if ($normalizedDirection !== 'ASC' && $normalizedDirection !== 'DESC') {
            throw new MapInvalidException('Sort direction is invalid');
        }

        return $normalizedDirection;
    }

    /**
     * Разбирает обязательный limit.
     *
     * @param array<string, mixed> $options Опции.
     *
     * @return int Limit.
     *
     * @throws MapInvalidException Если limit не 1..500.
     */
    private function parseLimit(array $options): int
    {
        if (!array_key_exists('limit', $options) || !is_int($options['limit'])) {
            throw new MapInvalidException('List query requires limit 1..500');
        }

        $limit = $options['limit'];
        if ($limit < 1 || $limit > 500) {
            throw new MapInvalidException('List query requires limit 1..500');
        }

        return $limit;
    }

    /**
     * Разбирает offset.
     *
     * @param mixed $offsetValue Сырой offset.
     *
     * @return int Offset.
     *
     * @throws MapInvalidException Если offset не int ≥ 0.
     */
    private function parseOffset(mixed $offsetValue): int
    {
        if (!is_int($offsetValue) || $offsetValue < 0) {
            throw new MapInvalidException('List query offset is invalid');
        }

        return $offsetValue;
    }

    /**
     * Разбирает countTotal.
     *
     * @param array<string, mixed> $options Опции.
     *
     * @return bool Флаг COUNT.
     *
     * @throws MapInvalidException Если значение не bool.
     */
    private function parseCountTotal(array $options): bool
    {
        if (!array_key_exists('countTotal', $options)) {
            return false;
        }

        if ($options['countTotal'] !== true && $options['countTotal'] !== false) {
            throw new MapInvalidException('countTotal must be bool');
        }

        return $options['countTotal'];
    }

    /**
     * Разбирает select.
     *
     * @param array<string, mixed> $options Опции.
     *
     * @return array<int, string>|null Поля или null.
     *
     * @throws MapInvalidException Если select пуст или не list имён.
     */
    private function parseSelect(array $options): ?array
    {
        if (!array_key_exists('select', $options)) {
            return null;
        }

        $selectValue = $options['select'];
        if (!is_array($selectValue) || $selectValue === [] || !array_is_list($selectValue)) {
            throw new MapInvalidException('Select must be a non-empty list of field names');
        }

        return $this->uniqueSelectNames($selectValue);
    }

    /**
     * Проверяет list имён select.
     *
     * @param array<int, mixed> $selectValue Сырой select.
     *
     * @return array<int, string> Имена.
     *
     * @throws MapInvalidException Если имя неверно или дубль.
     */
    private function uniqueSelectNames(array $selectValue): array
    {
        $selectedNames = [];
        foreach ($selectValue as $fieldName) {
            $this->assertFieldName($fieldName, 'Select field name is invalid');
            if (in_array($fieldName, $selectedNames, true)) {
                throw new MapInvalidException('Select field name is duplicated');
            }

            $selectedNames[] = $fieldName;
        }

        return $selectedNames;
    }

    /**
     * Проверяет шаблон имени поля.
     *
     * @param mixed $fieldName Кандидат.
     * @param string $message Текст ошибки.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя не строка-шаблон.
     */
    private function assertFieldName(mixed $fieldName, string $message): void
    {
        if (!is_string($fieldName) || preg_match('/^[a-z][a-z0-9_]*$/', $fieldName) !== 1) {
            throw new MapInvalidException($message);
        }
    }
}
