<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Illuminate\Database\Schema\Builder;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Interface\Service\ITableCatalog;
use Mifrial\Core\SmartTable\Service\Catalog\SmartTableCatalog;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Table\MetaTableDefinition;
use PHPUnit\Framework\TestCase;

final class DictionaryUniqueMysqlTest extends TestCase
{
    private ?ITableCatalog $catalog = null;

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
            $this->connectCatalog();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for SmartTable tests');
        }

        $this->dropFixtureTables();
        $this->catalog()->installMeta();
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
     * createTable с кортежем отвергает дубль пары.
     *
     * @return void
     */
    public function testCreateCompositeUnique(): void
    {
        $this->catalog()->createTable(
            'st_dict_probe',
            [
                ['name' => 'user_id', 'type' => 'int', 'required' => true],
                ['name' => 'group_id', 'type' => 'int', 'required' => true],
            ],
            [['user_id', 'group_id']],
        );
        self::assertContains(
            'st_dict_probe_user_id_group_id_unq',
            $this->indexNames('st_dict_probe'),
        );
        $records = $this->catalog()->openByName('st_dict_probe')->records();
        $records->add(['user_id' => 1, 'group_id' => 2]);
        try {
            $records->add(['user_id' => 1, 'group_id' => 2]);
            self::fail('duplicate pair');
        } catch (UniqueConstraintException $exception) {
            self::assertSame('UNIQUE_CONSTRAINT', $exception->getErrorCode());
        }
    }

    /**
     * setUniqueKeys на уже созданный стол добавляет индекс; dropField в ключе — отказ.
     *
     * @return void
     */
    public function testSetUniqueKeysAndDropField(): void
    {
        $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'user_id', 'type' => 'int', 'required' => true],
            ['name' => 'group_id', 'type' => 'int', 'required' => true],
        ]);
        $this->catalog()->setUniqueKeys('st_dict_probe', [['user_id', 'group_id']]);
        self::assertContains(
            'st_dict_probe_user_id_group_id_unq',
            $this->indexNames('st_dict_probe'),
        );
        try {
            $this->catalog()->dropField('st_dict_probe', 'user_id');
            self::fail('drop field in key');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        $this->catalog()->setUniqueKeys('st_dict_probe', []);
        $this->catalog()->dropField('st_dict_probe', 'user_id');
        self::assertNotContains('user_id', $this->schema()->getColumnListing('st_dict_probe'));
    }

    /**
     * NULL unique_keys в meta читается как пустой список.
     *
     * @return void
     */
    public function testNullUniqueKeysReadAsEmpty(): void
    {
        $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'title', 'type' => 'string'],
        ]);
        $meta = $this->gateway()->open(MetaTableDefinition::class);
        $row = $meta->records()->getUnique(ListQuery::fromOptions([
            'filter' => ['name' => 'st_dict_probe'],
            'limit' => 1,
        ]));
        self::assertIsArray($row);
        $meta->records()->update((int) $row['id'], ['unique_keys' => null]);
        $this->catalog()->openByName('st_dict_probe');
        $catalog = $this->catalog();
        self::assertInstanceOf(SmartTableCatalog::class, $catalog);
        self::assertSame([], $catalog->definitionByName('st_dict_probe')->getUniqueKeys());
    }

    /**
     * Собирает каталог из env или boot.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectCatalog(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $this->databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $this->databaseConnection->ping();
            $this->catalog = GatewayHarness::makeCatalog($this->databaseConnection);
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
        $resolvedCatalog = $container->get(ITableCatalog::class);
        self::assertInstanceOf(ITableCatalog::class, $resolvedCatalog);
        $this->catalog = $resolvedCatalog;
        $resolvedGateway = $container->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $resolvedGateway);
        $this->gateway = $resolvedGateway;
    }

    /**
     * Каталог после setUp.
     *
     * @return ITableCatalog Каталог.
     */
    private function catalog(): ITableCatalog
    {
        self::assertInstanceOf(ITableCatalog::class, $this->catalog);

        return $this->catalog;
    }

    /**
     * Шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function gateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->gateway);

        return $this->gateway;
    }

    /**
     * Schema builder тестового соединения.
     *
     * @return Builder Строитель.
     */
    private function schema(): Builder
    {
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);

        return $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
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
        $indexes = $this->schema()->getIndexes($tableName);
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

        $schemaBuilder = $this->schema();
        $schemaBuilder->dropIfExists('st_dict_probe');
        $schemaBuilder->dropIfExists('st_meta_field');
        $schemaBuilder->dropIfExists('st_meta_table');
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
