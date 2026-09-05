<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Dto\MinField;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Dto\SumField;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\AggregateItemTable;
use Mifrial\Core\SmartTable\Tests\Fixture\AggregateLooseReaderTable;
use Mifrial\Core\SmartTable\Tests\Fixture\AggregatePostTable;
use Mifrial\Core\SmartTable\Tests\Fixture\AggregateReaderTable;
use PHPUnit\Framework\TestCase;

final class AggregateMysqlTest extends TestCase
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
     * COUNT и SUM по group_id; промах ключа — пустой набор.
     *
     * @return void
     */
    public function testCountSumAndMissingGroup(): void
    {
        $records = $this->seedItems();
        $rows = $records->aggregate($this->countQuery([10, 20]))->rows();
        self::assertSame(
            [
                ['group_id' => 10, 'n' => 2],
                ['group_id' => 20, 'n' => 1],
            ],
            $rows,
        );
        $sums = $records->aggregate(AggregateQuery::fromOptions([
            'filter' => ['group_id' => [10, 20]],
            'group' => ['group_id'],
            'select' => ['group_id', new SumField('amount', 'total')],
            'limit' => 10,
        ]))->rows();
        self::assertSame(30, $sums[0]['total']);
        self::assertSame([], $records->aggregate($this->countQuery([99]))->rows());
    }

    /**
     * MAX(id) и MAX(datetime) гидратятся.
     *
     * @return void
     */
    public function testMaxIdAndDatetime(): void
    {
        $records = $this->seedItems();
        $rows = $records->aggregate(AggregateQuery::fromOptions([
            'filter' => ['group_id' => 10],
            'group' => ['group_id'],
            'select' => [
                'group_id',
                new MaxField('id', 'last_id'),
                new MinField('amount', 'lo'),
                new MaxField('created', 'last_at'),
            ],
            'limit' => 10,
        ]))->rows();
        self::assertIsInt($rows[0]['last_id']);
        self::assertSame(10, $rows[0]['lo']);
        self::assertInstanceOf(UnixDateTime::class, $rows[0]['last_at']);
    }

    /**
     * getList отвергает CountField; OuterColumn вне подзапроса — MAP_INVALID.
     *
     * @return void
     */
    public function testGetListRejectsMeasureAndBareOuter(): void
    {
        $records = $this->seedItems();
        try {
            $records->getList(ListQuery::fromOptions([
                'limit' => 10,
                'select' => [new CountField('n')],
            ]));
            self::fail('getList must reject CountField');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $records->getList(ListQuery::fromOptions([
                'limit' => 10,
                'filter' => ['group_id' => new OuterColumn('group_id')],
            ]));
            self::fail('bare OuterColumn must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Подзапрос + coalesce 0 в aggregate и getList.
     *
     * @return void
     */
    public function testSubqueryUnreadCoalesce(): void
    {
        $posts = $this->seedUnread();
        $unread = $posts->aggregate($this->unreadQuery(7, [1, 2]))->rows();
        self::assertSame(2, $unread[0]['unread']);
        self::assertSame(2, $unread[1]['unread']);
        $after = $posts->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => [
                'thread_id' => 1,
                '>id' => $this->lastReadSubquery(7),
            ],
        ]))->rows();
        self::assertCount(2, $after);
    }

    /**
     * Две строки подзапроса — ROW_WRITE_FAILED.
     *
     * @return void
     */
    public function testDuplicateSubqueryRows(): void
    {
        $gateway = $this->gateway();
        $posts = $gateway->open(AggregatePostTable::class);
        $readers = $gateway->open(AggregateLooseReaderTable::class);
        $posts->schema()->createTable();
        $readers->schema()->createTable();
        $posts->records()->add(['thread_id' => 1]);
        $readers->records()->add(['thread_id' => 1, 'user_id' => 7, 'last_read_id' => 1]);
        $readers->records()->add(['thread_id' => 1, 'user_id' => 7, 'last_read_id' => 2]);
        try {
            $posts->records()->aggregate(AggregateQuery::fromOptions([
                'filter' => [
                    'thread_id' => 1,
                    '>id' => new SubqueryValue(
                        AggregateLooseReaderTable::class,
                        'last_read_id',
                        filter: [
                            'thread_id' => new OuterColumn('thread_id'),
                            'user_id' => 7,
                        ],
                        coalesce: 0,
                    ),
                ],
                'group' => ['thread_id'],
                'select' => ['thread_id', new CountField('unread')],
                'limit' => 10,
            ]));
            self::fail('duplicate subquery rows must fail');
        } catch (RowWriteFailedException $exception) {
            self::assertSame('ROW_WRITE_FAILED', $exception->getErrorCode());
        }
    }

    /**
     * CountField: update note — hit; add — miss. Max: update amount — miss.
     *
     * @return void
     */
    public function testCacheTagsHitAndMiss(): void
    {
        $records = $this->seedItems();
        $countQuery = $this->countQuery([10]);
        self::assertSame(2, $records->aggregate($countQuery, 60)->rows()[0]['n']);
        $rowId = $records->getList(ListQuery::fromOptions([
            'limit' => 1,
            'filter' => ['group_id' => 10],
        ]))->rows()[0]['id'];
        $records->update($rowId, ['note' => 'changed']);
        self::assertSame(2, $records->aggregate($countQuery, 60)->rows()[0]['n']);
        $records->add([
            'group_id' => 10,
            'amount' => 1,
            'title' => 'extra',
            'created' => UnixDateTime::fromUnix(1_700_000_040),
        ]);
        self::assertSame(3, $records->aggregate($countQuery, 60)->rows()[0]['n']);

        $maxQuery = AggregateQuery::fromOptions([
            'filter' => ['group_id' => 10],
            'group' => ['group_id'],
            'select' => ['group_id', new MaxField('amount', 'hi')],
            'limit' => 10,
        ]);
        self::assertSame(20, $records->aggregate($maxQuery, 60)->rows()[0]['hi']);
        $records->update($rowId, ['amount' => 50]);
        self::assertSame(50, $records->aggregate($maxQuery, 60)->rows()[0]['hi']);
    }

    /**
     * Update last_read на чужой карте сбивает unread.
     *
     * @return void
     */
    public function testCacheSubqueryMissesOnReaderUpdate(): void
    {
        $posts = $this->seedUnread();
        $query = $this->unreadQuery(7, [1]);
        self::assertSame(2, $posts->aggregate($query, 60)->rows()[0]['unread']);
        $reader = $this->gateway()->open(AggregateReaderTable::class)->records();
        $member = $reader->getList(ListQuery::fromOptions([
            'limit' => 1,
            'filter' => ['thread_id' => 1, 'user_id' => 7],
        ]))->rows()[0];
        $reader->update($member['id'], ['last_read_id' => $member['last_read_id'] + 1]);
        self::assertSame(1, $posts->aggregate($query, 60)->rows()[0]['unread']);
    }

    /**
     * MAX datetime после TTL остаётся DateTime; update note не сбивает.
     *
     * @return void
     */
    public function testCacheDatetimeMaxHit(): void
    {
        $records = $this->seedItems();
        $maxQuery = AggregateQuery::fromOptions([
            'filter' => ['group_id' => 10],
            'group' => ['group_id'],
            'select' => ['group_id', new MaxField('created', 'last_at')],
            'limit' => 10,
        ]);
        $first = $records->aggregate($maxQuery, 60)->rows()[0]['last_at'];
        self::assertInstanceOf(UnixDateTime::class, $first);
        $rowId = $records->getList(ListQuery::fromOptions([
            'limit' => 1,
            'filter' => ['group_id' => 10],
        ]))->rows()[0]['id'];
        $records->update($rowId, ['note' => 'cache-hit']);
        $hit = $records->aggregate($maxQuery, 60)->rows()[0]['last_at'];
        self::assertInstanceOf(UnixDateTime::class, $hit);
        self::assertSame($first->toUnix(), $hit->toUnix());
    }

    /**
     * NULL last_read без coalesce — промах; coalesce 0 считает все id.
     *
     * @return void
     */
    public function testNullLastReadCoalesce(): void
    {
        $posts = $this->seedNullLastRead();
        $without = AggregateQuery::fromOptions([
            'filter' => [
                'thread_id' => 3,
                '>id' => new SubqueryValue(
                    AggregateReaderTable::class,
                    'last_read_id',
                    filter: [
                        'thread_id' => new OuterColumn('thread_id'),
                        'user_id' => 7,
                    ],
                ),
            ],
            'group' => ['thread_id'],
            'select' => ['thread_id', new CountField('unread')],
            'limit' => 10,
        ]);
        self::assertSame([], $posts->aggregate($without)->rows());
        $withZero = AggregateQuery::fromOptions([
            'filter' => [
                'thread_id' => 3,
                '>id' => $this->lastReadSubquery(7),
            ],
            'group' => ['thread_id'],
            'select' => ['thread_id', new CountField('unread')],
            'limit' => 10,
        ]);
        self::assertSame(2, $posts->aggregate($withZero)->rows()[0]['unread']);
    }

    /**
     * В tx агрегат не читает и не пишет кэш; rollback оставляет старый hit.
     *
     * @return void
     */
    public function testAggregateSkipsCacheInTransaction(): void
    {
        $records = $this->seedItems();
        $countQuery = $this->countQuery([10]);
        self::assertSame(2, $records->aggregate($countQuery, 60)->rows()[0]['n']);
        try {
            $this->gateway()->transaction(function () use ($records, $countQuery): void {
                $records->add([
                    'group_id' => 10,
                    'amount' => 1,
                    'title' => 'tx',
                    'created' => UnixDateTime::fromUnix(1_700_000_050),
                ]);
                self::assertSame(3, $records->aggregate($countQuery, 60)->rows()[0]['n']);
                throw new MapInvalidException('rollback aggregate cache');
            });
            self::fail('transaction must rethrow');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        self::assertSame(2, $records->aggregate($countQuery, 60)->rows()[0]['n']);
    }

    /**
     * COUNT по двум группам.
     *
     * @param array<int, int> $groupIds Id групп.
     *
     * @return AggregateQuery Запрос.
     */
    private function countQuery(array $groupIds): AggregateQuery
    {
        return AggregateQuery::fromOptions([
            'filter' => ['group_id' => $groupIds],
            'group' => ['group_id'],
            'select' => ['group_id', new CountField('n')],
            'limit' => 10,
        ]);
    }

    /**
     * Unread-запрос постов.
     *
     * @param int $userId Актор.
     * @param array<int, int> $threadIds Треды.
     *
     * @return AggregateQuery Запрос.
     */
    private function unreadQuery(int $userId, array $threadIds): AggregateQuery
    {
        return AggregateQuery::fromOptions([
            'filter' => [
                'thread_id' => $threadIds,
                '>id' => $this->lastReadSubquery($userId),
            ],
            'group' => ['thread_id'],
            'select' => ['thread_id', new CountField('unread')],
            'limit' => 10,
        ]);
    }

    /**
     * Подзапрос last_read актора.
     *
     * @param int $userId Актор.
     *
     * @return SubqueryValue Операнд.
     */
    private function lastReadSubquery(int $userId): SubqueryValue
    {
        return new SubqueryValue(
            AggregateReaderTable::class,
            'last_read_id',
            filter: [
                'thread_id' => new OuterColumn('thread_id'),
                'user_id' => $userId,
            ],
            coalesce: 0,
        );
    }

    /**
     * Три item-строки двух групп.
     *
     * @return IOpenedRecords Порт.
     */
    private function seedItems(): IOpenedRecords
    {
        $table = $this->gateway()->open(AggregateItemTable::class);
        $table->schema()->createTable();
        $records = $table->records();
        $records->add([
            'group_id' => 10,
            'amount' => 10,
            'title' => 'a',
            'created' => UnixDateTime::fromUnix(1_700_000_000),
        ]);
        $records->add([
            'group_id' => 10,
            'amount' => 20,
            'title' => 'b',
            'created' => UnixDateTime::fromUnix(1_700_000_010),
        ]);
        $records->add([
            'group_id' => 20,
            'amount' => 5,
            'title' => 'c',
            'created' => UnixDateTime::fromUnix(1_700_000_020),
        ]);

        return $records;
    }

    /**
     * Посты и членство для unread.
     *
     * @return IOpenedRecords Посты.
     */
    private function seedUnread(): IOpenedRecords
    {
        $gateway = $this->gateway();
        $posts = $gateway->open(AggregatePostTable::class);
        $readers = $gateway->open(AggregateReaderTable::class);
        $posts->schema()->createTable();
        $readers->schema()->createTable();
        $posts->records()->add(['thread_id' => 1]);
        $posts->records()->add(['thread_id' => 1]);
        $posts->records()->add(['thread_id' => 1]);
        $posts->records()->add(['thread_id' => 2]);
        $posts->records()->add(['thread_id' => 2]);
        $firstId = $posts->records()->getList(ListQuery::fromOptions([
            'limit' => 1,
            'filter' => ['thread_id' => 1],
            'sort' => ['id' => 'asc'],
        ]))->rows()[0]['id'];
        $readers->records()->add(['thread_id' => 1, 'user_id' => 7, 'last_read_id' => $firstId]);
        $readers->records()->add(['thread_id' => 2, 'user_id' => 7, 'last_read_id' => 0]);

        return $posts->records();
    }

    /**
     * Тред с NULL last_read у члена.
     *
     * @return IOpenedRecords Посты.
     */
    private function seedNullLastRead(): IOpenedRecords
    {
        $gateway = $this->gateway();
        $posts = $gateway->open(AggregatePostTable::class);
        $readers = $gateway->open(AggregateReaderTable::class);
        $posts->schema()->createTable();
        $readers->schema()->createTable();
        $posts->records()->add(['thread_id' => 3]);
        $posts->records()->add(['thread_id' => 3]);
        $readers->records()->add(['thread_id' => 3, 'user_id' => 7]);

        return $posts->records();
    }

    /**
     * Собирает шлюз из env или boot с file-cache.
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
                'path' => sys_get_temp_dir() . '/mifrial-st-agg-cache-' . uniqid('', true),
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
     * Удаляет таблицы фикстур.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->databaseConnection instanceof IlluminateDatabaseConnection) {
            return;
        }

        $schema = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $schema->dropIfExists('st_agg_item');
        $schema->dropIfExists('st_agg_post');
        $schema->dropIfExists('st_agg_reader');
        $schema->dropIfExists('st_agg_loose_reader');
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
