<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Field;

/**
 * Required-поле получило null.
 */
final class FieldRequiredException extends FieldException
{
    /**
     * Создаёт ошибку обязательности.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('FIELD_REQUIRED', 'Required field cannot be null');
    }
}
