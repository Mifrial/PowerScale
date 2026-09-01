<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Map;

use Mifrial\Core\SmartTable\Exception\SmartTableException;

/**
 * Некорректное определение таблицы или имени поля.
 */
final class MapInvalidException extends SmartTableException
{
    /**
     * Создаёт ошибку карты полей.
     *
     * @param string $message Уточнение.
     *
     * @return void
     */
    public function __construct(string $message = 'SmartTable map is invalid')
    {
        parent::__construct('MAP_INVALID', $message);
    }
}
