<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Field;

/**
 * Оператор или сортировка недопустимы для multiple.
 */
final class FieldMultipleUnsupportedException extends FieldException
{
    /**
     * Создаёт ошибку multiple.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('FIELD_MULTIPLE_UNSUPPORTED', 'Operator is not allowed on a multiple field');
    }
}
