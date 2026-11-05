<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Support;

use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use RuntimeException;

/**
 * Не даёт тестовым сносам таблиц уйти в dev-БД.
 *
 * Помощники тестов вызывают guard перед DROP/TRUNCATE. Маркер ставит
 * `tests/bootstrap.php`; ручной скрипт с `ApplicationFactory->boot()`
 * его не получает и падает до первого DROP.
 */
final class TestDatabaseGuard
{
    /**
     * Константа, которую ставит tests/bootstrap.php после подключения.
     *
     * @var string
     */
    public const MARKER = 'MIFRIAL_TEST_DATABASE';

    /**
     * Пропускает только процесс, прошедший tests/bootstrap.php.
     *
     * @return void
     *
     * @throws RuntimeException Если bootstrap не подключался к тестовой БД.
     */
    public static function assertTestBootstrap(): void
    {
        if (defined(self::MARKER)) {
            return;
        }

        throw new RuntimeException(
            'Refusing to drop tables: ' . self::MARKER . ' is not defined. '
            . 'Run through phpunit and check config/test.php.',
        );
    }

    /**
     * Проверяет bootstrap и то, что соединение не смотрит в dev-БД.
     *
     * @param IlluminateDatabaseConnection $connection Соединение тестов.
     *
     * @return void
     *
     * @throws RuntimeException Если это dev-БД или нет маркера bootstrap.
     */
    public static function assertSafeConnection(IlluminateDatabaseConnection $connection): void
    {
        self::assertTestBootstrap();
        $config = $connection->illuminateConnection()->getConfig();
        $config = is_array($config) ? $config : [];
        self::assertNotDevDatabase(
            (string) ($config['host'] ?? ''),
            (int) ($config['port'] ?? 3306),
            (string) ($config['database'] ?? ''),
        );
    }

    /**
     * Сверяет host/port/database с config/local.php.
     *
     * @param string $host Хост.
     * @param int $port Порт.
     * @param string $database Имя БД.
     *
     * @return void
     *
     * @throws RuntimeException Если совпало с dev-БД.
     */
    public static function assertNotDevDatabase(string $host, int $port, string $database): void
    {
        $local = self::localDatabase();
        if ($local === null) {
            return;
        }

        $same = $host === (string) ($local['host'] ?? '')
            && $port === (int) ($local['port'] ?? 3306)
            && $database === (string) ($local['database'] ?? '');
        if (!$same) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to drop tables in the dev database "%s" on %s:%d.',
            $database,
            $host,
            $port,
        ));
    }

    /**
     * Срез db из config/local.php.
     *
     * @return array<string, mixed>|null Конфиг или null, если файла нет.
     */
    private static function localDatabase(): ?array
    {
        $file = dirname(__DIR__, 5) . '/config/local.php';
        if (!is_file($file)) {
            return null;
        }

        $config = require $file;
        $db = is_array($config) ? ($config['db'] ?? null) : null;

        return is_array($db) ? $db : null;
    }
}
