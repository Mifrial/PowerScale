<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\MiniTitleNoteTable;
use PHPUnit\Framework\TestCase;

final class CacheMysqlTest extends TestCase
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
     * Hit TTL, update поля, add, delete, null get, rollback.
     *
     * @return void
     */
    public function testTaggedCacheAgainstMysql(): void
    {
        $table = $this->gateway()->open(MiniTitleNoteTable::class);
        $table->createTable();
        $rowId = $table->add(['title' => 'one', 'note' => 'n']);
        $titleQuery = ListQuery::fromOptions(['limit' => 10, 'select' => ['title']]);
        $noteQuery = ListQuery::fromOptions(['limit' => 10, 'select' => ['note']]);
        $table->getList($titleQuery, 60);
        $table->getList($noteQuery, 60);
        $table->update($rowId, ['title' => 'two']);
        self::assertSame('two', $table->getList($titleQuery, 60)->rows()[0]['title']);
        self::assertSame('n', $table->getList($noteQuery, 60)->rows()[0]['note']);

        $table->add(['title' => 'three', 'note' => 'm']);
        self::assertCount(2, $table->getList($titleQuery, 60)->rows());

        $cached = $table->get($rowId, 60);
        self::assertNotNull($cached);
        $table->delete($rowId);
        self::assertNull($table->get($rowId, 60));

        $missingId = $rowId + 100;
        self::assertNull($table->get($missingId, 60));
        self::assertNull($table->get($missingId, 60));

        $this->assertRollbackKeepsCache($table, $titleQuery);
        $this->assertNoTtlReadsDatabase($table);
    }

    /**
     * Собирает шлюз из env или boot, file-cache во временном каталоге.
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
            $this->bindCachedGateway();

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
        $this->bindCachedGateway();
    }

    /**
     * Шлюз с file-store в temp.
     *
     * @return void
     */
    private function bindCachedGateway(): void
    {
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);
        $this->gateway = GatewayHarness::make(
            $this->databaseConnection,
            CacheSettings::fromConfig([
                'driver' => 'file',
                'path' => sys_get_temp_dir() . '/mifrial-st-mysql-cache-' . uniqid('', true),
            ]),
            true,
        );
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
     * Rollback не сбрасывает уже записанный список.
     *
     * @param IOpenedTable $table Handle.
     * @param ListQuery $titleQuery Кэшированный запрос title.
     *
     * @return void
     */
    private function assertRollbackKeepsCache(IOpenedTable $table, ListQuery $titleQuery): void
    {
        $table->getList($titleQuery, 60);
        try {
            $this->gateway()->transaction(function () use ($table): void {
                $table->add(['title' => 'tx', 'note' => 't']);
                throw new MapInvalidException('rollback cache probe');
            });
            self::fail('transaction must rethrow');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        $titles = [];
        foreach ($table->getList($titleQuery, 60)->rows() as $row) {
            $titles[] = $row['title'];
        }

        self::assertNotContains('tx', $titles);
    }

    /**
     * Без TTL запись не читается из кэша как устаревшая после прямой БД.
     *
     * @param IOpenedTable $table Handle.
     *
     * @return void
     */
    private function assertNoTtlReadsDatabase(IOpenedTable $table): void
    {
        $rowId = $table->add(['title' => 'fresh', 'note' => 'z']);
        $table->get($rowId, 60);
        $table->update($rowId, ['title' => 'db']);
        self::assertSame('db', $table->get($rowId)['title'] ?? null);
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

        $this->databaseConnection->illuminateConnection()->getSchemaBuilder()->dropIfExists('st_crud_mini');
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
