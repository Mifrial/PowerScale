<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Service;

use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;

/**
 * Внутреннее ленивое соединение с MySQL без SQL-API для соседей.
 */
interface IDatabaseConnection
{
    /**
     * Проверяет, что соединение живое.
     *
     * @return void
     *
     * @throws DatabaseException Если конфиг или сервер недоступны.
     */
    public function ping(): void;
}
