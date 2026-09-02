<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use PHPUnit\Framework\TestCase;

final class MysqlPingTest extends TestCase
{
    /**
     * Проверяет живой MySQL или пропускает suite без сервера.
     *
     * @return void
     */
    public function testPingSelectsOne(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $this->pingSettings($this->settingsFromEnv($envHost));

            return;
        }

        $app = (new ApplicationFactory())->boot($this->mifrialRoot());
        $container = $app->getLocator()->get(ISmartTableContainer::class);
        self::assertInstanceOf(ISmartTableContainer::class, $container);
        self::assertTrue($app->getLocator()->has(IKernelContainer::class));

        $connection = $container->get(IDatabaseConnection::class);
        self::assertInstanceOf(IDatabaseConnection::class, $connection);
        $this->pingOrSkip($connection);
    }

    /**
     * Возвращает корень Mifrial.
     *
     * @return string Путь к www/mifrial.
     */
    private function mifrialRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Собирает настройки из переменных окружения теста.
     *
     * @param string $host Хост из MIFRIAL_TEST_DB_HOST.
     *
     * @return DatabaseSettings Настройки тестовой БД.
     */
    private function settingsFromEnv(string $host): DatabaseSettings
    {
        $port = getenv('MIFRIAL_TEST_DB_PORT');

        return DatabaseSettings::fromFields(
            $host,
            is_string($port) && ctype_digit($port) ? (int) $port : 3306,
            (string) getenv('MIFRIAL_TEST_DB_DATABASE'),
            (string) getenv('MIFRIAL_TEST_DB_USERNAME'),
            (string) getenv('MIFRIAL_TEST_DB_PASSWORD'),
            (string) getenv('MIFRIAL_TEST_DB_CHARSET'),
        );
    }

    /**
     * Пингует адаптер, собранный из явных настроек.
     *
     * @param DatabaseSettings $databaseSettings Настройки теста.
     *
     * @return void
     */
    private function pingSettings(DatabaseSettings $databaseSettings): void
    {
        $this->pingOrSkip(new IlluminateDatabaseConnection(
            new IlluminateConnectionFactory(),
            $databaseSettings,
        ));
    }

    /**
     * Выполняет ping или skip без сервера.
     *
     * @param IDatabaseConnection $connection Адаптер модуля.
     *
     * @return void
     */
    private function pingOrSkip(IDatabaseConnection $connection): void
    {
        try {
            $connection->ping();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for SmartTable tests');
        }
    }
}
