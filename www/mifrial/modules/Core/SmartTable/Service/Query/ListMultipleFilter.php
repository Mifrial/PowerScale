<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Closure;
use Illuminate\Database\Query\Builder;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldMultipleUnsupportedException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Service\Schema\MfvSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Contains и равенство множеств на mfv.
 */
final class ListMultipleFilter
{
    /**
     * Вешает `@` или `=` на multiple-поле.
     *
     * @param Builder $query Билдер основной таблицы.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Multiple-поле.
     * @param string $boolean And или or.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $ownerIdSql Квалифицированный id владельца или пусто.
     *
     * @return void
     *
     * @throws MapInvalidException Если оператор или пустой contains.
     * @throws FieldInvalidException Если операнд не list/скаляр.
     * @throws FieldMultipleUnsupportedException Если оператор не @/=.
     */
    public function apply(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
        SmartTableDefinition $tableDefinition,
        string $ownerIdSql = '',
    ): void {
        $operator = $condition->operator();
        if ($operator === '@') {
            $this->applyContains($query, $condition, $field, $boolean, $tableDefinition, $ownerIdSql);

            return;
        }

        if ($operator === '=') {
            $this->applyEquals($query, $condition, $field, $boolean, $tableDefinition, $ownerIdSql);

            return;
        }

        throw new FieldMultipleUnsupportedException();
    }

    /**
     * S ⊆ M через EXISTS.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean And или or.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $ownerIdSql Id владельца mfv.
     *
     * @return void
     *
     * @throws MapInvalidException Если contains пуст или null.
     */
    private function applyContains(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
        SmartTableDefinition $tableDefinition,
        string $ownerIdSql,
    ): void {
        $extractedValues = $this->extractedSet($field, $condition->operand(), false);
        $ownerIdSql = $this->ownerIdSql($tableDefinition, $ownerIdSql);
        $this->attachGroup($query, $boolean, function (Builder $nestedQuery) use (
            $tableDefinition,
            $field,
            $extractedValues,
            $ownerIdSql,
        ): void {
            foreach ($extractedValues as $extractedValue) {
                $this->whereValueExists($nestedQuery, $tableDefinition, $field, $extractedValue, 'and', $ownerIdSql);
            }
        });
    }

    /**
     * Накладывает равенство множеств.
     *
     * @param Builder $query Билдер.
     * @param FilterCondition $condition Условие.
     * @param BaseField $field Поле.
     * @param string $boolean And или or.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $ownerIdSql Id владельца mfv.
     *
     * @return void
     *
     * @throws MapInvalidException Если операнд null.
     */
    private function applyEquals(
        Builder $query,
        FilterCondition $condition,
        BaseField $field,
        string $boolean,
        SmartTableDefinition $tableDefinition,
        string $ownerIdSql,
    ): void {
        $extractedValues = $this->extractedSet($field, $condition->operand(), true);
        $ownerIdSql = $this->ownerIdSql($tableDefinition, $ownerIdSql);
        $this->attachGroup(
            $query,
            $boolean,
            function (Builder $nestedQuery) use ($tableDefinition, $field, $extractedValues, $ownerIdSql): void {
                $this->whereSetEquals($nestedQuery, $tableDefinition, $field, $extractedValues, $ownerIdSql);
            },
        );
    }

    /**
     * Вешает группу AND/OR одним предикатом.
     *
     * @param Builder $query Билдер.
     * @param string $boolean And или or.
     * @param Closure $group Вложенные условия.
     *
     * @return void
     */
    private function attachGroup(Builder $query, string $boolean, Closure $group): void
    {
        if ($boolean === 'or') {
            $query->orWhere($group);

            return;
        }

        $query->where($group);
    }

    /**
     * Равенство множества внутри уже скобочной группы.
     *
     * @param Builder $query Вложенный билдер.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param BaseField $field Поле.
     * @param array<int, mixed> $extractedValues Набор S.
     * @param string $ownerIdSql Id владельца.
     *
     * @return void
     */
    private function whereSetEquals(
        Builder $query,
        SmartTableDefinition $tableDefinition,
        BaseField $field,
        array $extractedValues,
        string $ownerIdSql,
    ): void {
        $mfvName = MfvSchema::tableName($tableDefinition, $field);
        if ($extractedValues === []) {
            $query->whereNotExists(function (Builder $subQuery) use ($mfvName, $ownerIdSql): void {
                $subQuery->from($mfvName)->whereRaw('`' . $mfvName . '`.owner_id = ' . $ownerIdSql);
            });

            return;
        }

        $valueCount = count($extractedValues);
        $placeholders = implode(', ', array_fill(0, $valueCount, '?'));
        $quotedMfv = '`' . $mfvName . '`';
        $countSql = '(select count(*) from ' . $quotedMfv
            . ' where owner_id = ' . $ownerIdSql . ') = ?';
        $inSql = '(select count(*) from ' . $quotedMfv
            . ' where owner_id = ' . $ownerIdSql . ' and `value` in (' . $placeholders . ')) = ?';
        $bindings = array_merge([$valueCount], $extractedValues, [$valueCount]);
        $query->whereRaw('(' . $countSql . ' and ' . $inSql . ')', $bindings);
    }

    /**
     * EXISTS одного value.
     *
     * @param Builder $query Билдер.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param BaseField $field Поле.
     * @param mixed $extractedValue Значение SQL.
     * @param string $boolean And или or.
     * @param string $ownerIdSql Квалифицированный id владельца или пусто.
     *
     * @return void
     */
    private function whereValueExists(
        Builder $query,
        SmartTableDefinition $tableDefinition,
        BaseField $field,
        mixed $extractedValue,
        string $boolean,
        string $ownerIdSql,
    ): void {
        $mfvName = MfvSchema::tableName($tableDefinition, $field);
        $query->whereExists(function (Builder $subQuery) use ($mfvName, $ownerIdSql, $extractedValue): void {
            $subQuery->from($mfvName)
                ->whereRaw('`' . $mfvName . '`.owner_id = ' . $ownerIdSql)
                ->where('value', $extractedValue);
        }, $boolean);
    }

    /**
     * Квалифицирует id владельца mfv.
     *
     * @param SmartTableDefinition $tableDefinition Карта владельца.
     * @param string $ownerIdSql Уже заданный SQL или пусто.
     *
     * @return string SQL выражения id.
     */
    private function ownerIdSql(SmartTableDefinition $tableDefinition, string $ownerIdSql): string
    {
        if ($ownerIdSql !== '') {
            return $ownerIdSql;
        }

        return '`' . $tableDefinition->getName() . '`.`id`';
    }

    /**
     * Скаляр или list → уникальный extract-набор.
     *
     * @param BaseField $field Поле.
     * @param mixed $operand Операнд API.
     * @param bool $allowEmpty Пустой list допустим.
     *
     * @return array<int, mixed> Значения SQL.
     *
     * @throws MapInvalidException Если null / пустой contains / дубль.
     * @throws FieldInvalidException Если форма неверна.
     */
    private function extractedSet(BaseField $field, mixed $operand, bool $allowEmpty): array
    {
        if ($operand === null) {
            throw new MapInvalidException('Multiple filter cannot be null');
        }

        if (!is_array($operand)) {
            return [$this->extractedItem($field, $operand)];
        }

        if (!array_is_list($operand)) {
            throw new FieldInvalidException('Multiple filter must be a list');
        }

        if ($operand === [] && !$allowEmpty) {
            throw new MapInvalidException('Contains filter cannot be empty');
        }

        $extractedValues = [];
        foreach ($operand as $itemValue) {
            $extractedValue = $this->extractedItem($field, $itemValue);
            if (in_array($extractedValue, $extractedValues, true)) {
                throw new MapInvalidException('Multiple list cannot contain duplicates');
            }

            $extractedValues[] = $extractedValue;
        }

        return $extractedValues;
    }

    /**
     * cast+extract одного элемента без required пустого множества.
     *
     * @param BaseField $field Поле.
     * @param mixed $itemValue Элемент.
     *
     * @return mixed Значение SQL.
     *
     * @throws FieldInvalidException Если элемент недопустим.
     */
    private function extractedItem(BaseField $field, mixed $itemValue): mixed
    {
        if ($itemValue === null) {
            throw new FieldInvalidException('Multiple list cannot contain null');
        }

        return $field->extract($field->cast([$itemValue], true))[0];
    }
}
