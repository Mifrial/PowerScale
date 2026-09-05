<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Синтаксический разбор опций aggregate без карты таблицы.
 */
final class AggregateQueryOptionsParser
{
    /**
     * @var array<int, string>
     */
    private const OPTION_KEYS = ['filter', 'group', 'select', 'limit'];

    /**
     * Собирает AggregateQuery из массива.
     *
     * @param array<string, mixed> $options Опции.
     *
     * @return AggregateQuery Запрос.
     *
     * @throws MapInvalidException Если синтаксис неверен.
     */
    public function parse(array $options): AggregateQuery
    {
        $this->assertKnownOptionKeys($options);
        $groupNames = $this->parseGroupNames($options);
        $selectItems = $this->parseSelect($options, $groupNames);

        return new AggregateQuery(
            (new FilterTreeParser())->parseOptional($options['filter'] ?? null),
            $groupNames,
            $selectItems,
            $this->parseLimit($options),
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
                throw new MapInvalidException('Unknown aggregate query option');
            }
        }
    }

    /**
     * Разбирает обязательный group.
     *
     * @param array<string, mixed> $options Опции.
     *
     * @return array<int, string> Имена.
     *
     * @throws MapInvalidException Если group пуст, не list или с точкой/дублем.
     */
    private function parseGroupNames(array $options): array
    {
        if (!array_key_exists('group', $options) || !is_array($options['group'])) {
            throw new MapInvalidException('Aggregate group is invalid');
        }

        $groupValue = $options['group'];
        if ($groupValue === [] || !array_is_list($groupValue)) {
            throw new MapInvalidException('Aggregate group is invalid');
        }

        $groupNames = [];
        foreach ($groupValue as $fieldName) {
            $groupNames[] = $this->uniqueGroupName($fieldName, $groupNames);
        }

        return $groupNames;
    }

    /**
     * Имя group без точки и дубля.
     *
     * @param mixed $fieldName Кандидат.
     * @param array<int, string> $groupNames Уже принятые.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя неверно или дубль.
     */
    private function uniqueGroupName(mixed $fieldName, array $groupNames): string
    {
        if (!is_string($fieldName) || preg_match('/^[a-z][a-z0-9_]*$/', $fieldName) !== 1) {
            throw new MapInvalidException('Aggregate group field is invalid');
        }

        if (in_array($fieldName, $groupNames, true)) {
            throw new MapInvalidException('Aggregate group field is duplicated');
        }

        return $fieldName;
    }

    /**
     * Разбирает select: все ключи group и хотя бы одна мера.
     *
     * @param array<string, mixed> $options Опции.
     * @param array<int, string> $groupNames Ключи GROUP BY.
     *
     * @return array<int, string|CountField|MaxField|MinField|SumField> Select.
     *
     * @throws MapInvalidException Если select не сходится с group.
     */
    private function parseSelect(array $options, array $groupNames): array
    {
        $selectValue = $this->selectList($options);
        $selectItems = [];
        $seenKeys = [];
        $hasMeasure = false;
        foreach ($selectValue as $selectItem) {
            $hasMeasure = $this->appendSelectItem($selectItems, $seenKeys, $selectItem, $groupNames) || $hasMeasure;
        }

        $this->assertGroupKeysPresent($groupNames, $seenKeys, $hasMeasure);

        return $selectItems;
    }

    /**
     * List select из опций.
     *
     * @param array<string, mixed> $options Опции.
     *
     * @return array<int, mixed> Элементы.
     *
     * @throws MapInvalidException Если select не list.
     */
    private function selectList(array $options): array
    {
        if (!array_key_exists('select', $options) || !is_array($options['select'])) {
            throw new MapInvalidException('Aggregate select is invalid');
        }

        $selectValue = $options['select'];
        if ($selectValue === [] || !array_is_list($selectValue)) {
            throw new MapInvalidException('Aggregate select is invalid');
        }

        return $selectValue;
    }

    /**
     * Добавляет элемент select и говорит, мера ли это.
     *
     * @param array<int, string|CountField|MaxField|MinField|SumField> $selectItems Уже собранные.
     * @param array<int, string> $seenKeys Занятые ключи.
     * @param mixed $selectItem Кандидат.
     * @param array<int, string> $groupNames Ключи GROUP BY.
     *
     * @return bool True, если мера.
     *
     * @throws MapInvalidException Если ключ дубль или элемент недопустим.
     */
    private function appendSelectItem(
        array &$selectItems,
        array &$seenKeys,
        mixed $selectItem,
        array $groupNames,
    ): bool {
        $itemKey = $this->selectItemKey($selectItem, $groupNames);
        if (in_array($itemKey, $seenKeys, true)) {
            throw new MapInvalidException('Aggregate select key is duplicated');
        }

        $seenKeys[] = $itemKey;
        $selectItems[] = $selectItem;

        return !is_string($selectItem);
    }

    /**
     * Ключ ряда одного элемента select.
     *
     * @param mixed $selectItem Строка или мера.
     * @param array<int, string> $groupNames Ключи GROUP BY.
     *
     * @return string Имя колонки или alias.
     *
     * @throws MapInvalidException Если элемент недопустим.
     */
    private function selectItemKey(mixed $selectItem, array $groupNames): string
    {
        if (is_string($selectItem)) {
            if (!in_array($selectItem, $groupNames, true)) {
                throw new MapInvalidException('Aggregate select field must be a group key');
            }

            return $selectItem;
        }

        if (
            $selectItem instanceof CountField
            || $selectItem instanceof MaxField
            || $selectItem instanceof MinField
            || $selectItem instanceof SumField
        ) {
            return $selectItem->alias();
        }

        throw new MapInvalidException('Aggregate select item is invalid');
    }

    /**
     * Все group в select и хотя бы одна мера.
     *
     * @param array<int, string> $groupNames Ключи GROUP BY.
     * @param array<int, string> $seenKeys Ключи select.
     * @param bool $hasMeasure Есть ли мера.
     *
     * @return void
     *
     * @throws MapInvalidException Если group не покрыт или мер нет.
     */
    private function assertGroupKeysPresent(array $groupNames, array $seenKeys, bool $hasMeasure): void
    {
        foreach ($groupNames as $groupName) {
            if (!in_array($groupName, $seenKeys, true)) {
                throw new MapInvalidException('Aggregate select must include all group keys');
            }
        }

        if (!$hasMeasure) {
            throw new MapInvalidException('Aggregate select requires a measure');
        }
    }

    /**
     * Разбирает обязательный limit групп.
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
            throw new MapInvalidException('Aggregate query requires limit 1..500');
        }

        $limit = $options['limit'];
        if ($limit < 1 || $limit > 500) {
            throw new MapInvalidException('Aggregate query requires limit 1..500');
        }

        return $limit;
    }
}
