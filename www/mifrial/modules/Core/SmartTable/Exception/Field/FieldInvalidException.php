<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Field;

/**
 * Значение поля не прошло cast/extract/hydrate.
 */
final class FieldInvalidException extends FieldException
{
    /**
     * Создаёт ошибку значения поля.
     *
     * @param string $message Уточнение.
     *
     * @return void
     */
    public function __construct(string $message = 'Field value is invalid')
    {
        parent::__construct('FIELD_INVALID', $message);
    }
}
