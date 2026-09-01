<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Illuminate\Database\Query\Builder;
use JsonException;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;

/**
 * JSON-операнд фильтра: encode и сравнение CAST AS JSON.
 */
final class ListJsonFilter
{
    /**
     * Сравнивает документ с колонкой JSON.
     *
     * @param Builder $query Билдер.
     * @param BaseField $field Поле JSON.
     * @param string $operator Equals или not-equals.
     * @param mixed $operand Документ API.
     * @param string $boolean And или or.
     *
     * @return void
     *
     * @throws MapInvalidException Если оператор не равенство.
     * @throws FieldInvalidException Если encode не удался.
     */
    public function applyCompare(
        Builder $query,
        BaseField $field,
        string $operator,
        mixed $operand,
        string $boolean,
    ): void {
        if ($operator !== '=' && $operator !== '!=') {
            throw new MapInvalidException('Filter operator is not allowed for field type');
        }

        $sqlOperator = $operator === '=' ? '=' : '<>';
        $sqlExpression = '`' . $field->name() . '` ' . $sqlOperator . ' cast(? as json)';
        $query->whereRaw(
            $sqlExpression,
            [$this->encodedOperand($field, $operand)],
            $boolean,
        );
    }

    /**
     * cast, extract и json_encode для драйвера.
     *
     * @param BaseField $field Поле.
     * @param mixed $operand Операнд API.
     *
     * @return string JSON-строка.
     *
     * @throws FieldInvalidException Если encode не удался.
     */
    public function encodedOperand(BaseField $field, mixed $operand): string
    {
        $extractedValue = $field->extract($field->cast($operand, true));
        try {
            return json_encode($extractedValue, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new FieldInvalidException('JSON value cannot be encoded');
        }
    }
}
