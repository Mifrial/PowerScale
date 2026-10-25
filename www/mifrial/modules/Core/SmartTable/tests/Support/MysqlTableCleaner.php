<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Support;

use Illuminate\Database\MySqlConnection;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;

/**
 * Очищает данные тестовой MySQL-БД между тестами.
 */
final class MysqlTableCleaner
{
    /**
     * Очищает base-таблицы текущей БД.
     *
     * Таблицы из $alwaysTruncate сбрасываются через TRUNCATE, чтобы ID
     * начинались с 1. Остальные таблицы очищаются через DELETE.
     *
     * @param IlluminateDatabaseConnection $connection Соединение тестовой БД.
     * @param array $alwaysTruncate Таблицы, которые сбрасываются через TRUNCATE.
     *
     * @return void
     */
    public static function truncateAll(IlluminateDatabaseConnection $connection, array $alwaysTruncate = []): void
    {
        $mySqlConnection = $connection->illuminateConnection();
        $tables = self::getTables($mySqlConnection);
        $mySqlConnection->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                if (in_array($table, $alwaysTruncate, true)) {
                    self::truncateTable($mySqlConnection, $table);
                    continue;
                }

                $mySqlConnection->statement('DELETE FROM ' . self::quote($table));
            }
        } finally {
            $mySqlConnection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Перечисляет base-таблицы текущей БД.
     *
     * @param MySqlConnection $connection Соединение.
     *
     * @return array Список имён таблиц.
     */
    private static function getTables(MySqlConnection $connection): array
    {
        $rows = $connection->select(
            'SELECT table_name AS name FROM information_schema.tables '
            . "WHERE table_schema = ? AND table_type = 'BASE TABLE'",
            [$connection->getDatabaseName()],
        );

        $tables = [];
        foreach ($rows as $row) {
            $name = $row->name ?? null;
            if (is_string($name) && $name !== '') {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * Сбрасывает таблицу вместе с auto-increment.
     *
     * @param MySqlConnection $connection Соединение.
     * @param string $table Имя таблицы.
     *
     * @return void
     */
    private static function truncateTable(MySqlConnection $connection, string $table): void
    {
        $connection->statement('TRUNCATE TABLE ' . self::quote($table));
    }

    /**
     * Заключает имя таблицы в обратные кавычки.
     *
     * @param string $table Имя таблицы.
     *
     * @return string Имя в обратных кавычках.
     */
    private static function quote(string $table): string
    {
        return '`' . str_replace('`', '``', $table) . '`';
    }
}
