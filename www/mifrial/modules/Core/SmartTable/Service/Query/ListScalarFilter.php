<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;

/**
 * Скалярное сравнение на имени колонки SQL.
 */
final class ListScalarFilter
{
    /**
     * Создаёт скалярный фильтр.
     *
     * @param ListJsonFilter $jsonFilter JSON CAST.
     *
     * @return void
     */
    public function __construct(
        private readonly ListJsonFilter $jsonFilter = new ListJsonFilter(),
    ) {
    }

    /**
     * Вешает IN, NULL или сравнение.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean And или or.
     * @param string $columnName Колонка SQL.
     *
     * @return void
     *
     * @throws MapInvalidException Если оператор недопустим.
     */
    public function apply(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
        string $columnName,
    ): void {
        if ($condition->operator() === '@') {
            throw new MapInvalidException('Contains prefix is not allowed on scalar fields');
        }

        $operand = $condition->operand();
        $operator = $condition->operator();
        if ($this->applyEqualityList($query, $field, $operand, $operator, $boolean, $columnName)) {
            return;
        }

        $this->assertOperatorAllowed($field->type(), $operator);
        if ($operand === null) {
            $this->applyNull($query, $columnName, $operator, $boolean);

            return;
        }

        $this->applyScalar($query, $condition, $field, $boolean, $columnName);
    }

    /**
     * Вешает IN, если операнд — list равенства.
     *
     * @param Builder $query Билдер.
     * @param BaseField $field Поле.
     * @param mixed $operand Операнд.
     * @param string $operator Оператор.
     * @param string $boolean Связка.
     * @param string $columnName Колонка.
     *
     * @return bool true, если условие уже повешено.
     *
     * @throws MapInvalidException Если list недопустим.
     */
    private function applyEqualityList(
        Builder $query,
        BaseField $field,
        mixed $operand,
        string $operator,
        string $boolean,
        string $columnName,
    ): bool {
        if (!is_array($operand) || !array_is_list($operand) || $field->type() === 'json') {
            return false;
        }

        if ($operator === '=') {
            $this->applyIn($query, $field, $operand, $boolean, $columnName);

            return true;
        }

        if ($operator !== '><') {
            throw new MapInvalidException('List operand is only allowed for IN and interval');
        }

        return false;
    }

    /**
     * Вешает SQL IN.
     *
     * @param Builder $query Билдер.
     * @param BaseField $field Поле.
     * @param array<int, mixed> $operandList Список.
     * @param string $boolean Связка.
     * @param string $columnName Колонка.
     *
     * @return void
     *
     * @throws MapInvalidException Если list пуст, с null или bool.
     */
    private function applyIn(
        Builder $query,
        BaseField $field,
        array $operandList,
        string $boolean,
        string $columnName,
    ): void {
        if ($operandList === [] || $field->type() === 'bool') {
            throw new MapInvalidException('IN list is invalid for this field');
        }

        $extractedValues = [];
        foreach ($operandList as $itemValue) {
            if ($itemValue === null) {
                throw new MapInvalidException('IN list cannot contain null');
            }

            $extractedValues[] = $field->extract($field->cast($itemValue, true));
        }

        $query->whereIn($columnName, $extractedValues, $boolean);
    }

    /**
     * Вешает IS NULL или IS NOT NULL.
     *
     * @param Builder $query Билдер.
     * @param string $columnName Колонка.
     * @param string $operator = или !=.
     * @param string $boolean Связка.
     *
     * @return void
     *
     * @throws MapInvalidException Если null при другом операторе.
     */
    private function applyNull(Builder $query, string $columnName, string $operator, string $boolean): void
    {
        if ($operator === '=') {
            $query->whereNull($columnName, $boolean);

            return;
        }

        if ($operator === '!=') {
            $query->whereNotNull($columnName, $boolean);

            return;
        }

        throw new MapInvalidException('Null is only allowed with equality operators');
    }

    /**
     * LIKE, JSON или BETWEEN/сравнение.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean Связка.
     * @param string $columnName Колонка.
     *
     * @return void
     */
    private function applyScalar(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
        string $columnName,
    ): void {
        $operator = $condition->operator();
        if ($field->type() === 'json') {
            $this->jsonFilter->applyCompare($query, $field, $operator, $condition->operand(), $boolean, $columnName);

            return;
        }

        if ($operator === '%') {
            $query->where($columnName, 'like', $field->extract($field->cast($condition->operand(), true)), $boolean);

            return;
        }

        $this->applyBetweenOrCompare($query, $condition, $field, $boolean, $columnName);
    }

    /**
     * BETWEEN или сравнение.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean Связка.
     * @param string $columnName Колонка.
     *
     * @return void
     *
     * @throws MapInvalidException Если интервал некорректен.
     */
    private function applyBetweenOrCompare(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
        string $columnName,
    ): void {
        if ($condition->operator() !== '><') {
            $query->where(
                $columnName,
                $condition->operator(),
                $this->sqlValue($field, $condition->operand()),
                $boolean,
            );

            return;
        }

        $this->applyInterval($query, $field, $condition->operand(), $boolean, $columnName);
    }

    /**
     * Вешает BETWEEN.
     *
     * @param Builder $query Билдер.
     * @param BaseField $field Поле.
     * @param mixed $operand Пара границ.
     * @param string $boolean Связка.
     * @param string $columnName Колонка.
     *
     * @return void
     *
     * @throws MapInvalidException Если интервал некорректен.
     */
    private function applyInterval(
        Builder $query,
        BaseField $field,
        mixed $operand,
        string $boolean,
        string $columnName,
    ): void {
        if (!is_array($operand) || !array_is_list($operand) || count($operand) !== 2) {
            throw new MapInvalidException('Interval filter requires two values');
        }

        $minimum = $this->sqlValue($field, $operand[0]);
        $maximum = $this->sqlValue($field, $operand[1]);
        if ($minimum > $maximum) {
            throw new MapInvalidException('Interval minimum is greater than maximum');
        }

        $query->whereBetween($columnName, [$minimum, $maximum], $boolean);
    }

    /**
     * Проверяет оператор типа.
     *
     * @param string $fieldType Тип.
     * @param string $operator Префикс.
     *
     * @return void
     *
     * @throws MapInvalidException Если оператор запрещён.
     */
    private function assertOperatorAllowed(string $fieldType, string $operator): void
    {
        $allowedOperators = match ($fieldType) {
            'string', 'text', 'html' => ['=', '!=', '%', '<', '>', '<=', '>=', '><'],
            'int', 'bigint', 'datetime', 'reference' => ['=', '!=', '<', '>', '<=', '>=', '><'],
            default => ['=', '!='],
        };
        if (!in_array($operator, $allowedOperators, true)) {
            throw new MapInvalidException('Filter operator is not allowed for field type');
        }
    }

    /**
     * cast+extract для SQL.
     *
     * @param BaseField $field Поле.
     * @param mixed $operand Операнд.
     *
     * @return mixed Значение драйвера.
     */
    private function sqlValue(BaseField $field, mixed $operand): mixed
    {
        return $field->extract($field->cast($operand, true));
    }
}
