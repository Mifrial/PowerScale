<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Exception\Field\FieldMultipleUnsupportedException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Filter по пути: вложенный EXISTS, FROM списка не трогает.
 */
final class ListPathFilter
{
    private int $aliasSerial = 0;

    /**
     * Создаёт фильтр путей.
     *
     * @param FieldPathWalker $fieldPathWalker Разбор пути.
     * @param ListFilterBinder $filterBinder Условие на листе.
     * @param ListMultipleFilter $multipleFilter Multiple на листе.
     *
     * @return void
     */
    public function __construct(
        private readonly FieldPathWalker $fieldPathWalker,
        private readonly ListFilterBinder $filterBinder,
        private readonly ListMultipleFilter $multipleFilter = new ListMultipleFilter(),
    ) {
    }

    /**
     * Вешает EXISTS по пути.
     *
     * @param Builder $query Билдер своей таблицы.
     * @param FilterCondition $condition Условие с путём.
     * @param string $boolean And или or.
     * @param SmartTableDefinition $tableDefinition Своя карта.
     *
     * @return void
     *
     * @throws MapInvalidException Если путь недопустим.
     * @throws FieldMultipleUnsupportedException Если оператор нельзя на multiple-листе.
     */
    public function apply(
        Builder $query,
        FilterCondition $condition,
        string $boolean,
        SmartTableDefinition $tableDefinition,
    ): void {
        $resolvedPath = $this->fieldPathWalker->resolve($tableDefinition, $condition->fieldName());
        $this->existsHop(
            $query,
            $resolvedPath,
            $condition,
            $boolean,
            0,
            $tableDefinition->getName(),
            $resolvedPath->hopFields()[0]->name(),
        );
    }

    /**
     * EXISTS одного hop.
     *
     * @param Builder $query Билдер.
     * @param ResolvedFieldPath $resolvedPath Путь.
     * @param FilterCondition $condition Условие.
     * @param string $boolean Связка.
     * @param int $hopIndex Индекс hop.
     * @param string $outerTable Внешний стол/алиас.
     * @param string $outerColumn Внешняя FK-колонка.
     *
     * @return void
     */
    private function existsHop(
        Builder $query,
        ResolvedFieldPath $resolvedPath,
        FilterCondition $condition,
        string $boolean,
        int $hopIndex,
        string $outerTable,
        string $outerColumn,
    ): void {
        $hopField = $resolvedPath->hopFields()[$hopIndex];
        $aliasName = 'st_e' . $this->aliasSerial;
        $this->aliasSerial++;
        $query->whereExists(
            fn (Builder $subQuery) => $this->fillExists(
                $subQuery,
                $resolvedPath,
                $condition,
                $hopIndex,
                $hopField,
                $aliasName,
                $outerTable,
                $outerColumn,
            ),
            $boolean,
        );
    }

    /**
     * FROM цели и корреляция / вложенность.
     *
     * @param Builder $subQuery EXISTS.
     * @param ResolvedFieldPath $resolvedPath Путь.
     * @param FilterCondition $condition Условие.
     * @param int $hopIndex Индекс.
     * @param ReferenceField $hopField Hop.
     * @param string $aliasName Алиас цели.
     * @param string $outerTable Внешний стол.
     * @param string $outerColumn Внешняя колонка.
     *
     * @return void
     */
    private function fillExists(
        Builder $subQuery,
        ResolvedFieldPath $resolvedPath,
        FilterCondition $condition,
        int $hopIndex,
        ReferenceField $hopField,
        string $aliasName,
        string $outerTable,
        string $outerColumn,
    ): void {
        $subQuery->from($hopField->targetTableName() . ' as ' . $aliasName)
            ->whereColumn($aliasName . '.' . $hopField->targetIdField(), $outerTable . '.' . $outerColumn);
        if ($hopIndex === count($resolvedPath->hopFields()) - 1) {
            $this->applyLeaf($subQuery, $resolvedPath, $condition, $aliasName);

            return;
        }

        $this->existsHop(
            $subQuery,
            $resolvedPath,
            $condition,
            'and',
            $hopIndex + 1,
            $aliasName,
            $resolvedPath->hopFields()[$hopIndex + 1]->name(),
        );
    }

    /**
     * Предикат листа на алиасе цели.
     *
     * @param Builder $subQuery EXISTS цели.
     * @param ResolvedFieldPath $resolvedPath Путь.
     * @param FilterCondition $condition Условие.
     * @param string $aliasName Алиас.
     *
     * @return void
     */
    private function applyLeaf(
        Builder $subQuery,
        ResolvedFieldPath $resolvedPath,
        FilterCondition $condition,
        string $aliasName,
    ): void {
        $leafField = $resolvedPath->leafField();
        $leafCondition = new FilterCondition($leafField->name(), $condition->operator(), $condition->operand());
        if (!$leafField->settings()->multiple()) {
            $this->filterBinder->applyOnColumn(
                $subQuery,
                $leafCondition,
                $leafField,
                'and',
                $aliasName . '.' . $leafField->name(),
            );

            return;
        }

        $this->multipleFilter->apply(
            $subQuery,
            $leafCondition,
            $leafField,
            'and',
            $resolvedPath->leafTable(),
            '`' . $aliasName . '`.`id`',
        );
    }
}
