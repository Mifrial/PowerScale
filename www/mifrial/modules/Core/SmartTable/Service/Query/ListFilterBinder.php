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
     * @param ListMultipleFilter $multipleFilter Contains и равенство mfv.
     * @param ListScalarFilter $scalarFilter Скаляр на колонке.
     *
     * @return void
     */
    public function __construct(
        private readonly ListMultipleFilter $multipleFilter = new ListMultipleFilter(),
        private readonly ListScalarFilter $scalarFilter = new ListScalarFilter(),
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

        $this->applyOnColumn($query, $condition, $field, $boolean, $field->name());
    }

    /**
     * Скалярное условие на квалифицированной колонке.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле листа.
     * @param string $boolean And или or.
     * @param string $columnName Колонка SQL.
     *
     * @return void
     *
     * @throws MapInvalidException Если оператор недопустим.
     */
    public function applyOnColumn(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
        string $columnName,
    ): void {
        $this->scalarFilter->apply($query, $condition, $field, $boolean, $columnName);
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
}
