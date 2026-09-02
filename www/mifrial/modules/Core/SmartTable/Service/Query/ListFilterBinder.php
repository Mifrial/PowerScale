<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Exception\Field\FieldMultipleUnsupportedException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Вешает одно условие фильтра на Query Builder.
 */
final class ListFilterBinder
{
    /**
     * Создаёт привязку условий.
     *
     * @param ListJsonFilter $jsonFilter JSON CAST-сравнение.
     * @param ListMultipleFilter $multipleFilter Contains и равенство mfv.
     *
     * @return void
     */
    public function __construct(
        private readonly ListJsonFilter $jsonFilter = new ListJsonFilter(),
        private readonly ListMultipleFilter $multipleFilter = new ListMultipleFilter(),
    ) {
    }

    /**
     * Добавляет условие с заданной связкой.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param string $boolean And или or.
     * @param SmartTableDefinition $tableDefinition Карта.
     *
     * @return void
     *
     * @throws MapInvalidException Если оператор или поле недопустимы.
     * @throws FieldMultipleUnsupportedException Если оператор нельзя на multiple.
     */
    public function apply(
        Builder $query,
        FilterCondition $condition,
        string $boolean,
        SmartTableDefinition $tableDefinition,
    ): void {
        $field = $this->mappedField($tableDefinition, $condition->fieldName());
        if ($field->settings()->multiple()) {
            $this->multipleFilter->apply($query, $condition, $field, $boolean, $tableDefinition);

            return;
        }

        if ($condition->operator() === '@') {
            throw new MapInvalidException('Contains prefix is not allowed on scalar fields');
        }

        $this->applyResolved($query, $condition, $field, $boolean);
    }

    /**
     * Берёт поле из карты.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param string $fieldName Имя.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если поля нет.
     */
    private function mappedField(SmartTableDefinition $tableDefinition, string $fieldName): BaseField
    {
        $fieldMap = $tableDefinition->getMap();
        if (!isset($fieldMap[$fieldName])) {
            throw new MapInvalidException('Unknown field name');
        }

        return $fieldMap[$fieldName];
    }

    /**
     * Выбирает IN, NULL или скалярное сравнение.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean And или or.
     *
     * @return void
     *
     * @throws MapInvalidException Если list на операторе без IN.
     */
    private function applyResolved(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
    ): void {
        $operand = $condition->operand();
        $operator = $condition->operator();
        if (is_array($operand) && array_is_list($operand) && $field->type() !== 'json') {
            if ($operator === '=') {
                $this->applyIn($query, $field, $operand, $boolean);

                return;
            }

            if ($operator !== '><') {
                throw new MapInvalidException('List operand is only allowed for IN and interval');
            }
        }

        $this->assertOperatorAllowed($field->type(), $operator);
        if ($operand === null) {
            $this->applyNull($query, $field->name(), $condition->operator(), $boolean);

            return;
        }

        $this->applyScalar($query, $condition, $field, $boolean);
    }

    /**
     * SQL IN для не-json list.
     *
     * @param Builder $query Билдер.
     * @param BaseField $field Поле.
     * @param array<int, mixed> $operandList Список значений.
     * @param string $boolean And или or.
     *
     * @return void
     *
     * @throws MapInvalidException Если list пуст, с null или bool.
     */
    private function applyIn(Builder $query, BaseField $field, array $operandList, string $boolean): void
    {
        if ($operandList === [] || $field->type() === 'bool') {
            throw new MapInvalidException('IN list is invalid for this field');
        }

        $extractedValues = [];
        foreach ($operandList as $itemValue) {
            if ($itemValue === null) {
                throw new MapInvalidException('IN list cannot contain null');
            }

            $extractedValues[] = $this->extractedOperand($field, $itemValue);
        }

        $query->whereIn($field->name(), $extractedValues, $boolean);
    }

    /**
     * IS NULL / IS NOT NULL без cast.
     *
     * @param Builder $query Билдер.
     * @param string $columnName Колонка.
     * @param string $operator = или !=.
     * @param string $boolean And или or.
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
     * Скалярное сравнение, LIKE или BETWEEN.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean And или or.
     *
     * @return void
     */
    private function applyScalar(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
    ): void {
        $operator = $condition->operator();
        if ($field->type() === 'json') {
            $this->jsonFilter->applyCompare($query, $field, $operator, $condition->operand(), $boolean);

            return;
        }

        if ($operator === '%') {
            $query->where($field->name(), 'like', $this->extractedOperand($field, $condition->operand()), $boolean);

            return;
        }

        $this->applyBetweenOrCompare($query, $condition, $field, $boolean);
    }

    /**
     * BETWEEN или обычное сравнение.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean And или or.
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
    ): void {
        if ($condition->operator() === '><') {
            $operand = $condition->operand();
            if (!is_array($operand) || !array_is_list($operand) || count($operand) !== 2) {
                throw new MapInvalidException('Interval filter requires two values');
            }

            $minimum = $this->extractedOperand($field, $operand[0]);
            $maximum = $this->extractedOperand($field, $operand[1]);
            if ($minimum > $maximum) {
                throw new MapInvalidException('Interval minimum is greater than maximum');
            }

            $query->whereBetween($field->name(), [$minimum, $maximum], $boolean);

            return;
        }

        $query->where(
            $field->name(),
            $condition->operator(),
            $this->extractedOperand($field, $condition->operand()),
            $boolean,
        );
    }

    /**
     * cast + extract для не-json операнда.
     *
     * @param BaseField $field Поле.
     * @param mixed $operand Операнд API.
     *
     * @return mixed Значение для SQL.
     */
    private function extractedOperand(BaseField $field, mixed $operand): mixed
    {
        return $field->extract($field->cast($operand, true));
    }

    /**
     * Проверяет оператор для нашего типа поля.
     *
     * @param string $fieldType Код типа.
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
            'int', 'datetime', 'reference' => ['=', '!=', '<', '>', '<=', '>=', '><'],
            default => ['=', '!='],
        };
        if (!in_array($operator, $allowedOperators, true)) {
            throw new MapInvalidException('Filter operator is not allowed for field type');
        }
    }
}
