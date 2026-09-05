<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Exception\Field\FieldMultipleUnsupportedException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * WHERE / ORDER / LIMIT списка на Query Builder.
 */
final class ListQueryCompiler
{
    private readonly ListPathFilter $pathFilter;

    private readonly FieldPathWalker $fieldPathWalker;

    private readonly ListPathSql $pathSql;

    private readonly ListSubqueryBinder $subqueryBinder;

    /**
     * Создаёт компилятор.
     *
     * @param ListFilterBinder $filterBinder Условия своей карты.
     * @param FieldPathWalker|null $fieldPathWalker Разбор пути.
     *
     * @return void
     */
    public function __construct(
        private readonly ListFilterBinder $filterBinder = new ListFilterBinder(),
        ?FieldPathWalker $fieldPathWalker = null,
    ) {
        $this->fieldPathWalker = $fieldPathWalker ?? new FieldPathWalker();
        $this->pathFilter = new ListPathFilter($this->fieldPathWalker, $this->filterBinder);
        $this->pathSql = new ListPathSql();
        $this->subqueryBinder = new ListSubqueryBinder($this->fieldPathWalker);
    }

    /**
     * Вешает WHERE по дереву фильтра.
     *
     * @param Builder $query Билдер.
     * @param ListQuery $listQuery Запрос.
     * @param SmartTableDefinition $tableDefinition Карта.
     *
     * @return void
     */
    public function applyWhere(
        Builder $query,
        ListQuery $listQuery,
        SmartTableDefinition $tableDefinition,
    ): void {
        $this->applyFilter($query, $listQuery->filter(), $tableDefinition);
    }

    /**
     * Вешает WHERE по уже разобранному дереву.
     *
     * @param Builder $query Билдер.
     * @param FilterGroup|null $filterGroup Дерево или нет WHERE.
     * @param SmartTableDefinition $tableDefinition Локальная карта.
     * @param SmartTableDefinition|null $outerTable Внешняя FROM подзапроса.
     *
     * @return void
     *
     * @throws MapInvalidException Если дерево или подзапрос недопустимы.
     */
    public function applyFilter(
        Builder $query,
        ?FilterGroup $filterGroup,
        SmartTableDefinition $tableDefinition,
        ?SmartTableDefinition $outerTable = null,
    ): void {
        if ($filterGroup instanceof FilterGroup) {
            $this->applyGroup($query, $filterGroup, 'and', $tableDefinition, $outerTable);
        }
    }

    /**
     * Вешает ORDER BY.
     *
     * @param Builder $query Билдер.
     * @param ListQuery $listQuery Запрос.
     * @param SmartTableDefinition $tableDefinition Карта.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле sort неизвестно.
     * @throws FieldMultipleUnsupportedException Если sort по multiple.
     */
    public function applyOrder(
        Builder $query,
        ListQuery $listQuery,
        SmartTableDefinition $tableDefinition,
    ): void {
        $sortMap = $listQuery->sort();
        if ($sortMap === []) {
            $query->orderBy('id', 'asc');

            return;
        }

        $fieldMap = $tableDefinition->getMap();
        foreach ($sortMap as $fieldName => $direction) {
            $this->applySortKey($query, $tableDefinition, $fieldMap, $fieldName, strtolower($direction));
        }
    }

    /**
     * Вешает OFFSET и LIMIT.
     *
     * @param Builder $query Билдер.
     * @param ListQuery $listQuery Запрос.
     *
     * @return void
     */
    public function applyPage(Builder $query, ListQuery $listQuery): void
    {
        $query->offset($listQuery->offset())->limit($listQuery->limit());
    }

    /**
     * Рекурсивно вешает группу AND/OR.
     *
     * @param Builder $query Билдер.
     * @param FilterGroup $filterGroup Группа.
     * @param string $boolean Связка с родителем.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param SmartTableDefinition|null $outerTable Внешняя FROM подзапроса.
     *
     * @return void
     */
    private function applyGroup(
        Builder $query,
        FilterGroup $filterGroup,
        string $boolean,
        SmartTableDefinition $tableDefinition,
        ?SmartTableDefinition $outerTable = null,
    ): void {
        $nested = function (Builder $nestedQuery) use ($filterGroup, $tableDefinition, $outerTable): void {
            $childBoolean = $filterGroup->logic() === 'OR' ? 'or' : 'and';
            foreach ($filterGroup->children() as $childNode) {
                if ($childNode instanceof FilterGroup) {
                    $this->applyGroup($nestedQuery, $childNode, $childBoolean, $tableDefinition, $outerTable);
                } elseif ($childNode instanceof FilterCondition) {
                    $this->applyCondition($nestedQuery, $childNode, $childBoolean, $tableDefinition, $outerTable);
                }
            }
        };
        if ($boolean === 'or') {
            $query->orWhere($nested);

            return;
        }

        $query->where($nested);
    }

    /**
     * Условие своей колонки или пути.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param string $boolean Связка.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param SmartTableDefinition|null $outerTable Внешняя FROM подзапроса.
     *
     * @return void
     *
     * @throws MapInvalidException Если подзапрос или путь недопустимы.
     */
    private function applyCondition(
        Builder $query,
        FilterCondition $condition,
        string $boolean,
        SmartTableDefinition $tableDefinition,
        ?SmartTableDefinition $outerTable = null,
    ): void {
        if ($this->applySpecialOperand($query, $condition, $boolean, $tableDefinition, $outerTable)) {
            return;
        }

        if ($outerTable instanceof SmartTableDefinition && str_contains($condition->fieldName(), '.')) {
            throw new MapInvalidException('Subquery filter path is invalid');
        }

        if (str_contains($condition->fieldName(), '.')) {
            $this->pathFilter->apply($query, $condition, $boolean, $tableDefinition);

            return;
        }

        $this->filterBinder->apply($query, $condition, $boolean, $tableDefinition);
    }

    /**
     * Подзапрос или OuterColumn.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param string $boolean Связка.
     * @param SmartTableDefinition $tableDefinition Локальная карта.
     * @param SmartTableDefinition|null $outerTable Внешняя FROM.
     *
     * @return bool True, если операнд обработан.
     *
     * @throws MapInvalidException Если подзапрос недопустим.
     */
    private function applySpecialOperand(
        Builder $query,
        FilterCondition $condition,
        string $boolean,
        SmartTableDefinition $tableDefinition,
        ?SmartTableDefinition $outerTable,
    ): bool {
        $operand = $condition->operand();
        if ($operand instanceof SubqueryValue) {
            $this->subqueryBinder->apply($query, $condition, $boolean, $tableDefinition, $outerTable, $this);

            return true;
        }

        if ($operand instanceof OuterColumn) {
            $this->subqueryBinder->applyOuter($query, $condition, $boolean, $tableDefinition, $outerTable);

            return true;
        }

        return false;
    }

    /**
     * ORDER BY своей колонки или подзапроса пути.
     *
     * @param Builder $query Билдер.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param array<string, BaseField> $fieldMap Своя карта.
     * @param string $fieldName Ключ sort.
     * @param string $direction asc|desc.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле неизвестно.
     * @throws FieldMultipleUnsupportedException Если multiple.
     */
    private function applySortKey(
        Builder $query,
        SmartTableDefinition $tableDefinition,
        array $fieldMap,
        string $fieldName,
        string $direction,
    ): void {
        if (str_contains($fieldName, '.')) {
            $resolvedPath = $this->fieldPathWalker->resolve($tableDefinition, $fieldName);
            if ($resolvedPath->leafField()->settings()->multiple()) {
                throw new FieldMultipleUnsupportedException();
            }

            $query->orderByRaw($this->pathSql->scalarSql($resolvedPath) . ' ' . $direction);

            return;
        }

        $this->assertSortField($fieldMap, $fieldName);
        $query->orderBy($fieldName, $direction);
    }

    /**
     * Проверяет поле сортировки своей карты.
     *
     * @param array<string, BaseField> $fieldMap Карта.
     * @param string $fieldName Имя.
     *
     * @return void
     *
     * @throws MapInvalidException Если поля нет.
     * @throws FieldMultipleUnsupportedException Если multiple.
     */
    private function assertSortField(array $fieldMap, string $fieldName): void
    {
        if (!isset($fieldMap[$fieldName])) {
            throw new MapInvalidException('Unknown field name');
        }

        if ($fieldMap[$fieldName]->settings()->multiple()) {
            throw new FieldMultipleUnsupportedException();
        }
    }
}
