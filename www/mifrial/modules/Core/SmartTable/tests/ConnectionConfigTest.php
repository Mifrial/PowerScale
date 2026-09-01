<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Service\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\IlluminateDatabaseConnection;
use PHPUnit\Framework\TestCase;

final class ConnectionConfigTest extends TestCase
{
    /**
     * Проверяет отказ при ключе dsn.
     *
     * @return void
     */
    public function testLegacyDsnIsRejectedWithoutConnecting(): void
    {
        $settings = DatabaseSettings::fromConfig([
            'host' => '127.0.0.1',
            'database' => 'powerscale',
            'dsn' => 'mysql:host=127.0.0.1',
        ]);

        try {
            (new IlluminateConnectionFactory())->open($settings);
            self::fail('dsn must be rejected');
        } catch (DbConfigInvalidException $exception) {
            self::assertSame('DB_CONFIG_INVALID', $exception->getErrorCode());
            self::assertStringNotContainsString('mysql:host', $exception->getMessage());
        }
    }

    /**
     * Проверяет отказ при пустых host и database.
     *
     * @return void
     */
    public function testEmptyHostIsRejected(): void
    {
        $adapter = new IlluminateDatabaseConnection(
            new IlluminateConnectionFactory(),
            DatabaseSettings::fromConfig([]),
        );

        try {
            $adapter->ping();
            self::fail('empty db must be rejected');
        } catch (DbConfigInvalidException $exception) {
            self::assertSame('DB_CONFIG_INVALID', $exception->getErrorCode());
        }
    }
}
