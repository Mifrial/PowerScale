<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\CrudProbeTable;
use PHPUnit\Framework\TestCase;

final class GetListMysqlTest extends TestCase
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
     * Проверяет =, !=, IN и LIKE.
     *
     * @return void
     */
    public function testEqualsInLike(): void
    {
        $table = $this->seedProbe();
        $equal = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['=title' => 'hello']]));
        self::assertCount(1, $equal->rows());
        self::assertSame('hello', $equal->rows()[0]['title']);
        self::assertNull($equal->total());

        $inList = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['id' => [
            $equal->rows()[0]['id'],
        ]]]));
        self::assertCount(1, $inList->rows());

        $like = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['%title' => '%hel%']]));
        self::assertGreaterThanOrEqual(2, count($like->rows()));

        $notHello = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['!=title' => 'hello']]));
        self::assertSame([], array_filter($notHello->rows(), static fn (array $row): bool => $row['title'] === 'hello'));
    }

    /**
     * Проверяет сравнения, интервал, OR, страницу и COUNT.
     *
     * @return void
     */
    public function testComparePageTotal(): void
    {
        $table = $this->seedProbe();
        $older = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['>age' => 12],
            'sort' => ['id' => 'desc'],
        ]));
        self::assertNotSame([], $older->rows());

        $between = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['><age' => [10, 15]]]));
        foreach ($between->rows() as $row) {
            self::assertGreaterThanOrEqual(10, $row['age']);
            self::assertLessThanOrEqual(15, $row['age']);
        }

        $orGroup = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['LOGIC' => 'OR', ['=title' => 'hello'], ['=title' => 'world']],
        ]));
        self::assertCount(2, $orGroup->rows());

        $page = $table->getList(ListQuery::fromOptions([
            'limit' => 1,
            'offset' => 1,
            'countTotal' => true,
            'sort' => ['id' => 'asc'],
            'select' => ['id', 'title'],
        ]));
        self::assertCount(1, $page->rows());
        self::assertArrayNotHasKey('age', $page->rows()[0]);
        self::assertSame(3, $page->total());
    }

    /**
     * Проверяет json =, datetime/bool, IS NULL required и пустую выборку.
     *
     * @return void
     */
    public function testJsonNullAndEmpty(): void
    {
        $table = $this->seedProbe();
        $jsonDoc = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['=payload' => ['a' => 1]]]));
        self::assertCount(1, $jsonDoc->rows());
        self::assertSame(['a' => 1], $jsonDoc->rows()[0]['payload']);

        $jsonArray = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['=payload' => [1, 2]]]));
        self::assertCount(1, $jsonArray->rows());

        $orJson = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['LOGIC' => 'OR', ['=payload' => ['a' => 1]], ['=payload' => ['b' => 2]]],
        ]));
        self::assertCount(2, $orJson->rows());

        $bools = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['=active' => true]]));
        self::assertNotSame([], $bools->rows());

        $nullTitle = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['=title' => null]]));
        self::assertSame([], $nullTitle->rows());

        $empty = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['=title' => 'missing']]));
        self::assertSame([], $empty->rows());

        $created = UnixDateTime::fromUnix(1700000000);
        $byCreated = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['=created' => $created]]));
        self::assertCount(3, $byCreated->rows());

        $newest = $table->getList(ListQuery::fromOptions([
            'limit' => 1,
            'sort' => ['id' => 'desc'],
        ]));
        $lastId = $newest->rows()[0]['id'];
        self::assertIsInt($lastId);
        $olderChat = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['<id' => $lastId],
            'sort' => ['id' => 'desc'],
        ]));
        self::assertCount(2, $olderChat->rows());
        foreach ($olderChat->rows() as $row) {
            self::assertLessThan($lastId, $row['id']);
        }
    }

    /**
     * Нет таблицы → TABLE_MISSING.
     *
     * @return void
     */
    public function testMissingTable(): void
    {
        $table = $this->gateway()->open(CrudProbeTable::class);
        try {
            $table->getList(ListQuery::fromOptions(['limit' => 10]));
            self::fail('missing table must fail');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }
    }

    /**
     * Создаёт таблицу и три строки.
     *
     * @return IOpenedTable Таблица.
     */
    private function seedProbe(): IOpenedTable
    {
        $table = $this->gateway()->open(CrudProbeTable::class);
        $table->createTable();
        $created = UnixDateTime::fromUnix(1700000000);
        $table->add([
            'title' => 'hello',
            'age' => 10,
            'active' => true,
            'created' => $created,
            'payload' => ['a' => 1],
        ]);
        $table->add([
            'title' => 'world',
            'age' => 20,
            'active' => false,
            'created' => $created,
            'payload' => ['b' => 2],
        ]);
        $table->add([
            'title' => 'help',
            'age' => 15,
            'active' => true,
            'created' => $created,
            'payload' => [1, 2],
        ]);

        return $table;
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
     * Удаляет таблицы фикстур.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->databaseConnection instanceof IlluminateDatabaseConnection) {
            return;
        }

        $this->databaseConnection->illuminateConnection()->getSchemaBuilder()->dropIfExists('st_crud_probe');
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
