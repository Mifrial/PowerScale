<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Support;

use Closure;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;

/**
 * Держит установленную схему тестовой БД по группам.
 */
final class MysqlSchemaRegistry
{
    /**
     * Установленные группы: ключ — имя БД и группа.
     *
     * @var array
     */
    private static array $installedGroups = [];

    /**
     * Устанавливает схему группы один раз.
     *
     * @param IlluminateDatabaseConnection $connection Соединение тестовой БД.
     * @param string $group Имя группы схемы.
     * @param array $sentinelTables Таблицы, по которым проверяется схема.
     * @param Closure $install Установка схемы.
     *
     * @return void
     *
     * @throws TableMissingException Если после установки таблицы нет.
     */
    public static function ensure(
        IlluminateDatabaseConnection $connection,
        string $group,
        array $sentinelTables,
        Closure $install,
    ): void {
        $key = self::getKey($connection, $group);
        if (isset(self::$installedGroups[$key]) && self::hasTables($connection, $sentinelTables)) {
            return;
        }

        $install();
        if (!self::hasTables($connection, $sentinelTables)) {
            throw new TableMissingException();
        }

        self::$installedGroups[$key] = true;
    }

    /**
     * Собирает ключ установленной группы.
     *
     * @param IlluminateDatabaseConnection $connection Соединение.
     * @param string $group Имя группы.
     *
     * @return string Ключ.
     */
    private static function getKey(IlluminateDatabaseConnection $connection, string $group): string
    {
        return $connection->illuminateConnection()->getDatabaseName() . ':' . $group;
    }

    /**
     * Проверяет наличие всех таблиц.
     *
     * @param IlluminateDatabaseConnection $connection Соединение.
     * @param array $tables Имена таблиц.
     *
     * @return bool true, если все таблицы есть.
     */
    private static function hasTables(IlluminateDatabaseConnection $connection, array $tables): bool
    {
        $schemaBuilder = $connection->illuminateConnection()->getSchemaBuilder();
        foreach ($tables as $table) {
            if (!$schemaBuilder->hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
