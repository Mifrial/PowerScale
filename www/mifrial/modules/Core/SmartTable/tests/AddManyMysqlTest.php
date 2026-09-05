<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\MiniTitleNoteTable;
use Mifrial\Core\SmartTable\Tests\Fixture\MultipleProbeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\UniqueTitleTable;
use PHPUnit\Framework\TestCase;

final class AddManyMysqlTest extends TestCase
{
    private ?ISmartTableGateway $gateway = null;

    private ?IlluminateDatabaseConnection $databaseConnection = null;

    /**
     * Подключается к MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectGateway();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for SmartTable tests');
        }

        $this->dropFixtureTables();
    }

    /**
     * Удаляет фикстурные таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropFixtureTables();
    }

    /**
     * Две строки, подряд id, unique на пачке, mfv в addMany.
     *
     * @return void
     */
    public function testInsertsTwoRowsAndRejectsUniqueAndMultiple(): void
    {
        $mini = $this->gateway()->open(MiniTitleNoteTable::class);
        $mini->schema()->createTable();
        $rowIds = $mini->records()->addMany([
            ['title' => 'one', 'note' => 'a'],
            ['title' => 'two'],
        ]);
        self::assertCount(2, $rowIds);
        self::assertSame($rowIds[0] + 1, $rowIds[1]);
        self::assertSame('one', $mini->records()->getById($rowIds[0])['title'] ?? null);
        self::assertSame('two', $mini->records()->getById($rowIds[1])['title'] ?? null);

        $unique = $this->gateway()->open(UniqueTitleTable::class);
        $unique->schema()->createTable();
        try {
            $unique->records()->addMany([
                ['title' => 'same', 'code' => 'c1'],
                ['title' => 'same', 'code' => 'c2'],
            ]);
            self::fail('duplicate title must fail');
        } catch (UniqueConstraintException $exception) {
            self::assertSame('UNIQUE_CONSTRAINT', $exception->getErrorCode());
        }

        $multiple = $this->gateway()->open(MultipleProbeTable::class);
        $multiple->schema()->createTable();
        $mfvIds = $multiple->records()->addMany([
            ['title' => 'a', 'tags' => ['x']],
            ['title' => 'b', 'tags' => ['y', 'x']],
        ]);
        self::assertSame(['x'], $multiple->records()->getById($mfvIds[0])['tags'] ?? null);
        self::assertSame(['x', 'y'], $multiple->records()->getById($mfvIds[1])['tags'] ?? null);
    }

    /**
     * Собирает шлюз из env или boot.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectGateway(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $this->databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $this->databaseConnection->ping();
            $this->gateway = GatewayHarness::make($this->databaseConnection);

            return;
        }

        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $container = $app->getLocator()->get(ISmartTableContainer::class);
        $connection = $container->get(IDatabaseConnection::class);
        if (!$connection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $connection->ping();
        $this->databaseConnection = $connection;
        $this->gateway = GatewayHarness::make($connection);
    }

    /**
     * Возвращает шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function gateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->gateway);

        return $this->gateway;
    }

    /**
     * Удаляет таблицы фикстур.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->databaseConnection instanceof IlluminateDatabaseConnection) {
            return;
        }

        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $schemaBuilder->dropIfExists('st_mfv_probe_mfv_tags');
        $schemaBuilder->dropIfExists('st_crud_mini');
        $schemaBuilder->dropIfExists('st_idx_unique');
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
        $collation = getenv('MIFRIAL_TEST_DB_COLLATION');
        $timezone = getenv('MIFRIAL_TEST_DB_TIMEZONE');

        return DatabaseSettings::fromFields(
            $host,
            is_string($port) && ctype_digit($port) ? (int) $port : 3306,
            (string) getenv('MIFRIAL_TEST_DB_DATABASE'),
            (string) getenv('MIFRIAL_TEST_DB_USERNAME'),
            (string) getenv('MIFRIAL_TEST_DB_PASSWORD'),
            (string) getenv('MIFRIAL_TEST_DB_CHARSET'),
            false,
            is_string($collation) && $collation !== '' ? $collation : 'utf8mb4_unicode_ci',
            is_string($timezone) && $timezone !== '' ? $timezone : '+00:00',
        );
    }
}
