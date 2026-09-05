<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\LinkSetNoneOwnerTable;
use Mifrial\Core\SmartTable\Tests\Fixture\LinkSetOwnerTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ParentRefTable;
use PHPUnit\Framework\TestCase;

final class LinkSetMysqlTest extends TestCase
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
     * add/addMany/getList; hop через linkset нельзя; FK restrict.
     *
     * @return void
     */
    public function testCrudFilterAndRejectsHopAndMissingTarget(): void
    {
        $parent = $this->gateway()->open(ParentRefTable::class);
        $owner = $this->gateway()->open(LinkSetOwnerTable::class);
        $parent->schema()->createTable();
        $owner->schema()->createTable();
        $firstId = $parent->records()->add(['title' => 'one']);
        $secondId = $parent->records()->add(['title' => 'two']);
        $rowId = $owner->records()->add(['title' => 'n', 'keywords' => [$secondId, $firstId]]);
        $row = $owner->records()->getById($rowId);
        self::assertIsArray($row);
        self::assertSame([$firstId, $secondId], $row['keywords']);

        $batchIds = $owner->records()->addMany([
            ['title' => 'a', 'keywords' => [$firstId]],
            ['title' => 'b', 'keywords' => [$firstId, $secondId]],
        ]);
        self::assertCount(2, $batchIds);
        self::assertSame([$firstId], $owner->records()->getById($batchIds[0])['keywords'] ?? null);

        $hasFirst = $owner->records()->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['@keywords' => $firstId],
        ]));
        self::assertCount(3, $hasFirst->rows());

        try {
            $owner->records()->getList(ListQuery::fromOptions([
                'limit' => 10,
                'filter' => ['keywords.title' => 'one'],
            ]));
            self::fail('hop through linkset must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $owner->records()->add(['title' => 'x', 'keywords' => [999999]]);
            self::fail('missing target must fail');
        } catch (ReferenceConstraintException $exception) {
            self::assertSame('REFERENCE_CONSTRAINT', $exception->getErrorCode());
        }

        try {
            $parent->records()->delete($firstId);
            self::fail('restrict must block delete');
        } catch (ReferenceConstraintException $exception) {
            self::assertSame('REFERENCE_CONSTRAINT', $exception->getErrorCode());
        }
    }

    /**
     * updateTable снимает leftover FK при onDelete none.
     *
     * @return void
     */
    public function testUpdateTableDropsLinksetForeignWhenNone(): void
    {
        $parent = $this->gateway()->open(ParentRefTable::class);
        $owner = $this->gateway()->open(LinkSetOwnerTable::class);
        $parent->schema()->createTable();
        $owner->schema()->createTable();
        $parentId = $parent->records()->add(['title' => 'one']);
        $owner->records()->add(['title' => 'n', 'keywords' => [$parentId]]);
        $this->gateway()->open(LinkSetNoneOwnerTable::class)->schema()->updateTable();
        $parent->records()->delete($parentId);
        self::assertNull($parent->records()->getById($parentId));
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
        $schemaBuilder->dropIfExists('st_linkset_owner_mfv_keywords');
        $schemaBuilder->dropIfExists('st_linkset_owner');
        $schemaBuilder->dropIfExists('st_ref_parent');
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
