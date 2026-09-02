<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldMultipleUnsupportedException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\PathChildTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathOwnerTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathParentTable;
use PHPUnit\Framework\TestCase;

final class ReferencePathMysqlTest extends TestCase
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
     * Удаляет фикстуры child → parent → owner.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropFixtureTables();
    }

    /**
     * Filter EXISTS, select/sort подзапрос, multiple-лист, countTotal, кэш тегов.
     *
     * @return void
     */
    public function testPathFilterSelectSortAndCache(): void
    {
        $handles = $this->createPathTables();
        $owner = $handles['owner']->records()->add(['login' => 'alice']);
        $liveParent = $handles['parent']->records()->add([
            'title' => 'live',
            'owner_id' => $owner,
            'tags' => ['red', 'blue'],
        ]);
        $deadParent = $handles['parent']->records()->add([
            'title' => 'dead',
            'active' => false,
            'tags' => ['red'],
        ]);
        $handles['child']->records()->add(['parent_id' => $liveParent]);
        $handles['child']->records()->add(['parent_id' => $liveParent]);
        $handles['child']->records()->add(['parent_id' => $deadParent]);
        $this->assertFilterAndCount($handles['child'], $liveParent);
        $this->assertSelectAndSort($handles['child']);
        $this->assertMultipleLeaf($handles['child']);
        $this->assertTwoHop($handles['child']);
        $this->assertCacheTags($handles);
    }

    /**
     * Sort по multiple-листу пути — отказ.
     *
     * @return void
     */
    public function testSortMultiplePathRejected(): void
    {
        $handles = $this->createPathTables();
        try {
            $handles['child']->records()->getList(ListQuery::fromOptions([
                'limit' => 10,
                'sort' => ['parent_id.tags' => 'asc'],
            ]));
            self::fail('sort multiple path must fail');
        } catch (FieldMultipleUnsupportedException $exception) {
            self::assertSame('FIELD_MULTIPLE_UNSUPPORTED', $exception->getErrorCode());
        }
    }

    /**
     * Filter и countTotal не размножают child.
     *
     * @param IOpenedTable $child Child.
     * @param int $liveParentId Id живого родителя.
     *
     * @return void
     */
    private function assertFilterAndCount(IOpenedTable $child, int $liveParentId): void
    {
        $activeList = $child->records()->getList(ListQuery::fromOptions([
            'limit' => 10,
            'countTotal' => true,
            'filter' => ['parent_id.active' => true],
        ]));
        self::assertSame(2, $activeList->total());
        self::assertCount(2, $activeList->rows());
        self::assertSame($liveParentId, $activeList->rows()[0]['parent_id']);
        self::assertArrayNotHasKey('parent_id.active', $activeList->rows()[0]);
        $ownOnly = $child->records()->getList(ListQuery::fromOptions([
            'limit' => 10,
            'select' => ['parent_id'],
        ]));
        self::assertCount(3, $ownOnly->rows());
        self::assertArrayNotHasKey('parent_id.active', $ownOnly->rows()[0]);
    }

    /**
     * Select и sort по скаляру родителя.
     *
     * @param IOpenedTable $child Child.
     *
     * @return void
     */
    private function assertSelectAndSort(IOpenedTable $child): void
    {
        $selected = $child->records()->getList(ListQuery::fromOptions([
            'limit' => 10,
            'select' => ['parent_id', 'parent_id.active'],
            'sort' => ['parent_id.title' => 'asc'],
        ]));
        self::assertFalse($selected->rows()[0]['parent_id.active']);
        self::assertTrue($selected->rows()[2]['parent_id.active']);
        self::assertIsInt($selected->rows()[0]['parent_id']);
    }

    /**
     * Multiple на листе цели.
     *
     * @param IOpenedTable $child Child.
     *
     * @return void
     */
    private function assertMultipleLeaf(IOpenedTable $child): void
    {
        $contains = $child->records()->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['@parent_id.tags' => 'blue'],
            'select' => ['parent_id.tags'],
        ]));
        self::assertCount(2, $contains->rows());
        self::assertEqualsCanonicalizing(['red', 'blue'], $contains->rows()[0]['parent_id.tags']);
    }

    /**
     * Два hop на login владельца.
     *
     * @param IOpenedTable $child Child.
     *
     * @return void
     */
    private function assertTwoHop(IOpenedTable $child): void
    {
        $byLogin = $child->records()->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['parent_id.owner_id.login' => 'alice'],
            'select' => ['parent_id.owner_id.login'],
        ]));
        self::assertCount(2, $byLogin->rows());
        self::assertSame('alice', $byLogin->rows()[0]['parent_id.owner_id.login']);
    }

    /**
     * Update title родителя бьёт путь; note — нет.
     *
     * @param array<string, IOpenedTable> $handles Таблицы.
     *
     * @return void
     */
    private function assertCacheTags(array $handles): void
    {
        $titleQuery = ListQuery::fromOptions([
            'limit' => 10,
            'select' => ['parent_id', 'parent_id.title'],
            'sort' => ['id' => 'asc'],
        ]);
        $rows = $handles['child']->records()->getList($titleQuery, 60)->rows();
        $parentId = $rows[0]['parent_id'];
        self::assertIsInt($parentId);
        $handles['parent']->records()->update($parentId, ['note' => 'x']);
        self::assertSame('live', $handles['child']->records()->getList($titleQuery, 60)->rows()[0]['parent_id.title']);
        $handles['parent']->records()->update($parentId, ['title' => 'gone']);
        self::assertSame('gone', $handles['child']->records()->getList($titleQuery, 60)->rows()[0]['parent_id.title']);
    }

    /**
     * Создаёт три таблицы пути.
     *
     * @return array{owner: IOpenedTable, parent: IOpenedTable, child: IOpenedTable} Handles.
     */
    private function createPathTables(): array
    {
        $owner = $this->gateway()->open(PathOwnerTable::class);
        $parent = $this->gateway()->open(PathParentTable::class);
        $child = $this->gateway()->open(PathChildTable::class);
        $owner->schema()->createTable();
        $parent->schema()->createTable();
        $child->schema()->createTable();

        return ['owner' => $owner, 'parent' => $parent, 'child' => $child];
    }

    /**
     * Подключается к тестовой БД с file-cache.
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
            $this->bindGateway();

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
        $this->bindGateway();
    }

    /**
     * Шлюз с file-кэшем.
     *
     * @return void
     */
    private function bindGateway(): void
    {
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);
        $this->gateway = GatewayHarness::make(
            $this->databaseConnection,
            CacheSettings::fromConfig([
                'driver' => 'file',
                'path' => sys_get_temp_dir() . '/mifrial-st-path-cache-' . uniqid('', true),
            ]),
            true,
        );
    }

    /**
     * Возвращает шлюз.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function gateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->gateway);

        return $this->gateway;
    }

    /**
     * Удаляет таблицы пути.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->databaseConnection instanceof IlluminateDatabaseConnection) {
            return;
        }

        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        foreach (['st_path_child', 'st_path_parent', 'st_path_owner'] as $tableName) {
            $schemaBuilder->dropIfExists($tableName);
            $schemaBuilder->dropIfExists($tableName . '_mfv_tags');
        }
    }

    /**
     * Настройки из env.
     *
     * @param string $envHost Хост.
     *
     * @return DatabaseSettings DSN.
     */
    private function settingsFromEnv(string $envHost): DatabaseSettings
    {
        return DatabaseSettings::fromConfig([
            'host' => $envHost,
            'port' => (int) (getenv('MIFRIAL_TEST_DB_PORT') ?: 3306),
            'database' => (string) getenv('MIFRIAL_TEST_DB_NAME'),
            'username' => (string) getenv('MIFRIAL_TEST_DB_USER'),
            'password' => (string) (getenv('MIFRIAL_TEST_DB_PASSWORD') ?: ''),
        ]);
    }
}
