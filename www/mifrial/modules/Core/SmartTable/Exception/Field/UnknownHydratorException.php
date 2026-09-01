<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Field;

/**
 * В реестре нет hydrator с таким кодом.
 */
final class UnknownHydratorException extends FieldException
{
    /**
     * Создаёт ошибку неизвестного hydrator.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('HYDRATOR_UNKNOWN', 'Unknown field hydrator');
    }
}
