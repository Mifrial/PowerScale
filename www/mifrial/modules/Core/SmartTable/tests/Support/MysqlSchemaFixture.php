<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Support;

use Closure;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;

/**
 * Готовит схему и данные тестовой MySQL.
 */
trait MysqlSchemaFixture
{
    /**
     * Ставит схему группы один раз и очищает данные.
     *
     * @param IlluminateDatabaseConnection $connection Соединение.
     * @param string $group Имя группы схемы.
     * @param array $sentinelTables Таблицы, по которым проверяется схема.
     * @param Closure $install Установка схемы.
     * @param array $alwaysTruncate Таблицы, которые сбрасываются через TRUNCATE.
     *
     * @return void
     */
    protected function prepareMysql(
        IlluminateDatabaseConnection $connection,
        string $group,
        array $sentinelTables,
        Closure $install,
        array $alwaysTruncate = [],
    ): void {
        MysqlSchemaRegistry::ensure($connection, $group, $sentinelTables, $install);
        MysqlTableCleaner::truncateAll($connection, $alwaysTruncate);
    }
}
