<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Разбор массива фильтра в дерево без карты таблицы.
 */
final class FilterTreeParser
{
    /**
     * @var array<int, string>
     */
    private const OPERATOR_PREFIXES = ['><', '<=', '>=', '!=', '@', '=', '%', '<', '>'];

    /**
     * Разбирает узел группы.
     *
     * @param array<string|int, mixed> $node Массив фильтра.
     *
     * @return FilterGroup Группа.
     *
     * @throws MapInvalidException Если дерево некорректно.
     */
    public function parseGroup(array $node): FilterGroup
    {
        $logic = 'AND';
        if (array_key_exists('LOGIC', $node)) {
            $logic = $this->parseLogic($node['LOGIC']);
            unset($node['LOGIC']);
        }

        $children = [];
        foreach ($node as $entryKey => $entryValue) {
            $children[] = $this->parseChild($entryKey, $entryValue);
        }

        if ($children === []) {
            throw new MapInvalidException('Filter group is empty');
        }

        return new FilterGroup($logic, $children);
    }

    /**
     * Разбирает LOGIC.
     *
     * @param mixed $logicValue Сырое значение.
     *
     * @return string AND или OR.
     *
     * @throws MapInvalidException Если связка неизвестна.
     */
    private function parseLogic(mixed $logicValue): string
    {
        if (!is_string($logicValue)) {
            throw new MapInvalidException('Filter LOGIC is invalid');
        }

        $normalizedLogic = strtoupper($logicValue);
        if ($normalizedLogic !== 'AND' && $normalizedLogic !== 'OR') {
            throw new MapInvalidException('Filter LOGIC is invalid');
        }

        return $normalizedLogic;
    }

    /**
     * Разбирает ребёнка группы.
     *
     * @param int|string $entryKey Ключ PHP-массива.
     * @param mixed $entryValue Значение.
     *
     * @return FilterGroup|FilterCondition Узел.
     *
     * @throws MapInvalidException Если узел некорректен.
     */
    private function parseChild(int|string $entryKey, mixed $entryValue): FilterGroup|FilterCondition
    {
        if (is_int($entryKey)) {
            if (!is_array($entryValue)) {
                throw new MapInvalidException('Nested filter group must be an array');
            }

            return $this->parseGroup($entryValue);
        }

        return $this->parseCondition($entryKey, $entryValue);
    }

    /**
     * Разбирает условие по ключу префикс+поле.
     *
     * @param string $filterKey Ключ диалекта.
     * @param mixed $operand Операнд.
     *
     * @return FilterCondition Условие.
     *
     * @throws MapInvalidException Если ключ или >< некорректны.
     */
    private function parseCondition(string $filterKey, mixed $operand): FilterCondition
    {
        [$operator, $fieldName] = $this->splitOperator($filterKey);
        if (preg_match('/^[a-z][a-z0-9_]*$/', $fieldName) !== 1) {
            throw new MapInvalidException('Filter field name is invalid');
        }

        if ($operator === '><') {
            $this->assertBetweenOperand($operand);
        }

        return new FilterCondition($fieldName, $operator, $operand);
    }

    /**
     * Отделяет префикс оператора от имени поля.
     *
     * @param string $filterKey Ключ диалекта.
     *
     * @return array{0: string, 1: string} Оператор и имя.
     *
     * @throws MapInvalidException Если префикс неизвестен.
     */
    private function splitOperator(string $filterKey): array
    {
        foreach (self::OPERATOR_PREFIXES as $prefix) {
            if (str_starts_with($filterKey, $prefix) && strlen($filterKey) > strlen($prefix)) {
                return [$prefix, substr($filterKey, strlen($prefix))];
            }
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $filterKey) === 1) {
            return ['=', $filterKey];
        }

        throw new MapInvalidException('Unknown filter operator prefix');
    }

    /**
     * Проверяет операнд BETWEEN.
     *
     * @param mixed $operand Кандидат.
     *
     * @return void
     *
     * @throws MapInvalidException Если это не пара list.
     */
    private function assertBetweenOperand(mixed $operand): void
    {
        if (!is_array($operand) || !array_is_list($operand) || count($operand) !== 2) {
            throw new MapInvalidException('Interval filter requires two values');
        }
    }
}
