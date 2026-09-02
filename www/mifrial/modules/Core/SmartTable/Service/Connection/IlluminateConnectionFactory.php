<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Connection;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\MySqlConnection;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;

/**
 * Сборка Illuminate MySQL-соединения без Capsule.
 */
final class IlluminateConnectionFactory
{
    /**
     * Открывает PDO и оборачивает его в MySqlConnection.
     *
     * @param DatabaseSettings $databaseSettings Настройки из RuntimeConfig.
     *
     * @return MySqlConnection Соединение Illuminate.
     *
     * @throws DbConfigInvalidException Если срез db непригоден.
     */
    public function open(DatabaseSettings $databaseSettings): MySqlConnection
    {
        $this->assertConnectable($databaseSettings);
        $connectionConfig = $this->illuminateConfig($databaseSettings);
        $pdo = (new MySqlConnector())->connect($connectionConfig);

        return new MySqlConnection($pdo, $databaseSettings->database(), '', $connectionConfig);
    }

    /**
     * Отклоняет dsn и пустые host/database.
     *
     * @param DatabaseSettings $databaseSettings Настройки соединения.
     *
     * @return void
     *
     * @throws DbConfigInvalidException Если конфиг нельзя использовать.
     */
    private function assertConnectable(DatabaseSettings $databaseSettings): void
    {
        if ($databaseSettings->hasLegacyDsn()) {
            throw new DbConfigInvalidException(
                'MySQL settings require host and database keys without dsn',
            );
        }

        if ($databaseSettings->host() === '' || $databaseSettings->database() === '') {
            throw new DbConfigInvalidException(
                'MySQL settings require host and database keys without dsn',
            );
        }
    }

    /**
     * Собирает массив конфига Illuminate.
     *
     * @param DatabaseSettings $databaseSettings Настройки соединения.
     *
     * @return array<string, mixed> Конфиг коннектора.
     */
    private function illuminateConfig(DatabaseSettings $databaseSettings): array
    {
        $locale = $databaseSettings->mysqlLocale();

        return [
            'driver' => 'mysql',
            'host' => $databaseSettings->host(),
            'port' => $databaseSettings->port() > 0 ? $databaseSettings->port() : 3306,
            'database' => $databaseSettings->database(),
            'username' => $databaseSettings->username(),
            'password' => $databaseSettings->password(),
            'charset' => $locale['charset'],
            'collation' => $locale['collation'],
            'timezone' => $locale['timezone'],
            'prefix' => '',
        ];
    }
}
