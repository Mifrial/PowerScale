<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Illuminate\Database\Schema\Blueprint;
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
use Mifrial\Core\SmartTable\Service\Schema\IndexSchema;
use Mifrial\Core\SmartTable\Tests\Fixture\BothIndexFlagsTable;
use Mifrial\Core\SmartTable\Tests\Fixture\IndexedAgeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\UniqueTitleTable;
use PHPUnit\Framework\TestCase;

final class IndexMysqlTest extends TestCase
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
     * CREATE unique и index по имени.
     *
     * @return void
     */
    public function testCreateIndexNames(): void
    {
        $uniqueTable = $this->gateway()->open(UniqueTitleTable::class);
        $indexedTable = $this->gateway()->open(IndexedAgeTable::class);
        $uniqueTable->schema()->createTable();
        $indexedTable->schema()->createTable();
        $uniqueNames = $this->indexNames('st_idx_unique');
        $indexedNames = $this->indexNames('st_idx_int');
        self::assertContains('st_idx_unique_title_unq', $uniqueNames);
        self::assertContains('st_idx_unique_code_unq', $uniqueNames);
        self::assertContains('st_idx_int_age_idx', $indexedNames);
        $bothTable = $this->gateway()->open(BothIndexFlagsTable::class);
        $bothTable->schema()->createTable();
        $bothNames = $this->indexNames('st_idx_both');
        self::assertContains('st_idx_both_title_unq', $bothNames);
        self::assertNotContains('st_idx_both_title_idx', $bothNames);
        $titleField = (new UniqueTitleTable())->getMap()['title'];
        $ageField = (new IndexedAgeTable())->getMap()['age'];
        self::assertSame('st_idx_unique_title_unq', IndexSchema::uniqueName(new UniqueTitleTable(), $titleField));
        self::assertSame('st_idx_int_age_idx', IndexSchema::indexName(new IndexedAgeTable(), $ageField));
    }

    /**
     * Дубль unique на add и update.
     *
     * @return void
     */
    public function testUniqueDuplicateOnAddAndUpdate(): void
    {
        $table = $this->gateway()->open(UniqueTitleTable::class);
        $table->schema()->createTable();
        $firstId = $table->records()->add(['title' => 'a']);
        $secondId = $table->records()->add(['title' => 'b']);
        try {
            $table->records()->add(['title' => 'a']);
            self::fail('duplicate unique add must fail');
        } catch (UniqueConstraintException $exception) {
            self::assertSame('UNIQUE_CONSTRAINT', $exception->getErrorCode());
        }
        self::assertNotNull($table->records()->getById($firstId));

        try {
            $table->records()->update($secondId, ['title' => 'a']);
            self::fail('duplicate unique update must fail');
        } catch (UniqueConstraintException $exception) {
            self::assertSame('UNIQUE_CONSTRAINT', $exception->getErrorCode());
        }
        self::assertSame('b', $table->records()->getById($secondId)['title']);
    }

    /**
     * Несколько NULL на nullable unique допустимы.
     *
     * @return void
     */
    public function testNullableUniqueAllowsMultipleNulls(): void
    {
        $table = $this->gateway()->open(UniqueTitleTable::class);
        $table->schema()->createTable();
        $firstId = $table->records()->add(['title' => 'a']);
        $secondId = $table->records()->add(['title' => 'b']);
        self::assertNull($table->records()->getById($firstId)['code']);
        self::assertNull($table->records()->getById($secondId)['code']);
    }

    /**
     * updateTable восстанавливает unique и index после drop.
     *
     * @return void
     */
    public function testUpdateTableRestoresIndexes(): void
    {
        $uniqueTable = $this->gateway()->open(UniqueTitleTable::class);
        $indexedTable = $this->gateway()->open(IndexedAgeTable::class);
        $uniqueTable->schema()->createTable();
        $indexedTable->schema()->createTable();
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        $schemaBuilder->table('st_idx_unique', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('st_idx_unique_title_unq');
        });
        $schemaBuilder->table('st_idx_int', function (Blueprint $blueprint): void {
            $blueprint->dropIndex('st_idx_int_age_idx');
        });
        $uniqueTable->schema()->updateTable();
        $indexedTable->schema()->updateTable();
        self::assertContains('st_idx_unique_title_unq', $this->indexNames('st_idx_unique'));
        self::assertContains('st_idx_int_age_idx', $this->indexNames('st_idx_int'));
    }

    /**
     * Подключается к тестовой БД.
     *
     * @return void
     *
     * @throws DatabaseException Если конфиг или ping не удались.
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
        $resolvedGateway = $container->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $resolvedGateway);
        $this->gateway = $resolvedGateway;
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
     * Имена индексов таблицы.
     *
     * @param string $tableName Имя.
     *
     * @return array<int, string> Имена.
     */
    private function indexNames(string $tableName): array
    {
        $indexes = $this->databaseConnection?->illuminateConnection()
            ->getSchemaBuilder()
            ->getIndexes($tableName);
        self::assertIsArray($indexes);
        $indexNames = [];
        foreach ($indexes as $index) {
            if (isset($index['name']) && is_string($index['name'])) {
                $indexNames[] = $index['name'];
            }
        }

        return $indexNames;
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
        $schemaBuilder->dropIfExists('st_idx_unique');
        $schemaBuilder->dropIfExists('st_idx_int');
        $schemaBuilder->dropIfExists('st_idx_both');
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
