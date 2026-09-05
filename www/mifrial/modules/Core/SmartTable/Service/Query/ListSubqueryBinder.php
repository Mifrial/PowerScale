<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Коррелированный скалярный подзапрос и OuterColumn в WHERE.
 */
final class ListSubqueryBinder
{
    /**
     * @var array<int, string>
     */
    private const COMPARE_OPERATORS = ['=', '!=', '<', '>', '<=', '>='];

    /**
     * Создаёт биндер подзапроса.
     *
     * @param FieldPathWalker $fieldPathWalker Карты class-string и словаря.
     *
     * @return void
     */
    public function __construct(
        private readonly FieldPathWalker $fieldPathWalker = new FieldPathWalker(),
    ) {
    }

    /**
     * Вешает сравнение с подзапросом на внешний WHERE.
     *
     * @param Builder $query Внешний билдер.
     * @param FilterCondition $condition Условие с SubqueryValue.
     * @param string $boolean And или or.
     * @param SmartTableDefinition $localTable Внешняя FROM.
     * @param SmartTableDefinition|null $outerTable Контекст вложенности.
     * @param ListQueryCompiler $compiler Внутренний WHERE.
     *
     * @return void
     *
     * @throws MapInvalidException Если подзапрос недопустим.
     * @throws FieldInvalidException Если coalesce не прошёл cast.
     */
    public function apply(
        Builder $query,
        FilterCondition $condition,
        string $boolean,
        SmartTableDefinition $localTable,
        ?SmartTableDefinition $outerTable,
        ListQueryCompiler $compiler,
    ): void {
        if ($outerTable instanceof SmartTableDefinition) {
            throw new MapInvalidException('Nested subquery is invalid');
        }

        if (str_contains($condition->fieldName(), '.')) {
            throw new MapInvalidException('Subquery on a field path is invalid');
        }

        $operand = $condition->operand();
        if (!$operand instanceof SubqueryValue) {
            throw new MapInvalidException('Subquery operand is invalid');
        }

        $this->assertCompareOperator($condition->operator());
        $innerTable = $this->fieldPathWalker->definitionFor($operand->table());
        $innerField = $this->scalarSelectField($innerTable, $operand->field());
        $innerQuery = $query->newQuery()->from($innerTable->getName())->select($operand->field());
        $compiler->applyFilter($innerQuery, $operand->filter(), $innerTable, $localTable);
        $this->bindComparison($query, $condition, $boolean, $innerQuery, $innerField, $operand->coalesce());
    }

    /**
     * Вешает сравнение с колонкой внешней FROM.
     *
     * @param Builder $query Внутренний билдер.
     * @param FilterCondition $condition Условие с OuterColumn.
     * @param string $boolean And или or.
     * @param SmartTableDefinition $localTable Внутренняя карта.
     * @param SmartTableDefinition|null $outerTable Внешняя FROM.
     *
     * @return void
     *
     * @throws MapInvalidException Если OuterColumn вне подзапроса или поле неизвестно.
     */
    public function applyOuter(
        Builder $query,
        FilterCondition $condition,
        string $boolean,
        SmartTableDefinition $localTable,
        ?SmartTableDefinition $outerTable,
    ): void {
        if (!$outerTable instanceof SmartTableDefinition) {
            throw new MapInvalidException('Outer column is invalid outside a subquery');
        }

        $operand = $condition->operand();
        if (!$operand instanceof OuterColumn) {
            throw new MapInvalidException('Outer column operand is invalid');
        }

        $this->assertCompareOperator($condition->operator());
        $this->requireOwnField($localTable, $condition->fieldName());
        $this->requireOwnField($outerTable, $operand->fieldName());
        $outerColumn = $outerTable->getName() . '.' . $operand->fieldName();
        $query->whereColumn($condition->fieldName(), $condition->operator(), $outerColumn, $boolean);
    }

    /**
     * Скалярное поле select подзапроса.
     *
     * @param SmartTableDefinition $innerTable Внутренняя карта.
     * @param string $fieldName Имя.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если поля нет или тип не скаляр.
     */
    private function scalarSelectField(SmartTableDefinition $innerTable, string $fieldName): BaseField
    {
        $field = $this->requireOwnField($innerTable, $fieldName);
        $fieldType = $field->type();
        if ($field->isMfv() || in_array($fieldType, ['json', 'text', 'html'], true)) {
            throw new MapInvalidException('Subquery select field is invalid');
        }

        return $field;
    }

    /**
     * Поле своей карты без пути.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $fieldName Имя.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если поля нет или это путь.
     */
    private function requireOwnField(SmartTableDefinition $tableDefinition, string $fieldName): BaseField
    {
        if (str_contains($fieldName, '.')) {
            throw new MapInvalidException('Subquery filter path is invalid');
        }

        $fieldMap = $tableDefinition->getMap();
        if (!isset($fieldMap[$fieldName])) {
            throw new MapInvalidException('Unknown field name');
        }

        return $fieldMap[$fieldName];
    }

    /**
     * Сравнение внешней колонки с SQL подзапроса.
     *
     * @param Builder $query Внешний билдер.
     * @param FilterCondition $condition Условие.
     * @param string $boolean Связка.
     * @param Builder $innerQuery Подзапрос.
     * @param BaseField $innerField Поле select.
     * @param mixed $coalesce COALESCE или null.
     *
     * @return void
     */
    private function bindComparison(
        Builder $query,
        FilterCondition $condition,
        string $boolean,
        Builder $innerQuery,
        BaseField $innerField,
        mixed $coalesce,
    ): void {
        $subSql = '(' . $innerQuery->toSql() . ')';
        $bindings = $innerQuery->getBindings();
        if ($coalesce === null) {
            $query->whereRaw(
                $condition->fieldName() . ' ' . $condition->operator() . ' ' . $subSql,
                $bindings,
                $boolean,
            );

            return;
        }

        $bindings[] = $innerField->extract($innerField->cast($coalesce, true));
        $query->whereRaw(
            $condition->fieldName() . ' ' . $condition->operator() . ' COALESCE(' . $subSql . ', ?)',
            $bindings,
            $boolean,
        );
    }

    /**
     * Допускает только сравнение, не IN/@/%/интервал.
     *
     * @param string $operator Префикс.
     *
     * @return void
     *
     * @throws MapInvalidException Если оператор недопустим.
     */
    private function assertCompareOperator(string $operator): void
    {
        if (!in_array($operator, self::COMPARE_OPERATORS, true)) {
            throw new MapInvalidException('Subquery operator is invalid');
        }
    }
}
