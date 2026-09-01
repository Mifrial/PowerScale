<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\IntMultipleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\MultipleProbeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\MultipleRequiredTable;
use PHPUnit\Framework\TestCase;

final class MultipleMysqlTest extends TestCase
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
     * CRUD mfv и порядок get.
     *
     * @return void
     */
    public function testAddGetUpdateDelete(): void
    {
        $table = $this->openProbe();
        $this->assertProbeStorage();
        $rowId = $table->add(['title' => 'n', 'tags' => ['b', 'a']]);
        $row = $table->get($rowId);
        self::assertIsArray($row);
        self::assertSame(['a', 'b'], $row['tags']);

        $table->update($rowId, ['tags' => ['c']]);
        self::assertSame(['c'], $table->get($rowId)['tags']);

        $emptyId = $table->add(['title' => 'e']);
        self::assertSame([], $table->get($emptyId)['tags']);

        $table->delete($rowId);
        self::assertNull($table->get($rowId));
        $leftover = $this->databaseConnection?->illuminateConnection()
            ->table('st_mfv_probe_mfv_tags')
            ->where('owner_id', $rowId)
            ->count();
        self::assertSame(0, $leftover);
    }

    /**
     * Required empty и update только tags.
     *
     * @return void
     */
    public function testRequiredAndPartialUpdate(): void
    {
        $required = $this->gateway()->open(MultipleRequiredTable::class);
        $required->createTable();
        try {
            $required->add([]);
            self::fail('required empty tags must fail');
        } catch (FieldRequiredException $exception) {
            self::assertSame('FIELD_REQUIRED', $exception->getErrorCode());
        }

        $probe = $this->openProbe();
        $rowId = $probe->add(['title' => 'n', 'tags' => ['a']]);
        $probe->update($rowId, ['tags' => ['z']]);
        $row = $probe->get($rowId);
        self::assertSame('n', $row['title']);
        self::assertSame(['z'], $row['tags']);
    }

    /**
     * Contains, равенство множеств и select.
     *
     * @return void
     */
    public function testContainsEqualsSelect(): void
    {
        $table = $this->openProbe();
        $table->add(['title' => 'one', 'tags' => ['a', 'b']]);
        $table->add(['title' => 'two', 'tags' => ['a', 'b', 'c']]);
        $table->add(['title' => 'empty']);

        $hasA = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['@tags' => 'a']]));
        self::assertCount(2, $hasA->rows());

        $hasAb = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['@tags' => ['a', 'b']]]));
        self::assertCount(2, $hasAb->rows());

        $hasAz = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['@tags' => ['a', 'z']]]));
        self::assertSame([], $hasAz->rows());

        try {
            $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['@tags' => []]]));
            self::fail('empty contains must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        $exactAb = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['tags' => ['a', 'b']]]));
        self::assertCount(1, $exactAb->rows());
        self::assertSame('one', $exactAb->rows()[0]['title']);

        $table->add(['title' => 'onlya', 'tags' => ['a']]);
        $scalarA = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['tags' => 'a']]));
        self::assertCount(1, $scalarA->rows());
        self::assertSame('onlya', $scalarA->rows()[0]['title']);

        $orListContains = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['LOGIC' => 'OR', ['@tags' => ['a', 'b']], ['=title' => 'empty']],
        ]));
        $orTitles = array_column($orListContains->rows(), 'title');
        sort($orTitles);
        self::assertSame(['empty', 'one', 'two'], $orTitles);

        $orEquals = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['LOGIC' => 'OR', ['tags' => ['a', 'b']], ['=title' => 'empty']],
        ]));
        $equalsTitles = array_column($orEquals->rows(), 'title');
        sort($equalsTitles);
        self::assertSame(['empty', 'one'], $equalsTitles);

        $emptySet = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['tags' => []]]));
        self::assertCount(1, $emptySet->rows());
        self::assertSame('empty', $emptySet->rows()[0]['title']);

        $orContains = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['LOGIC' => 'OR', ['@tags' => 'c'], ['=title' => 'empty']],
        ]));
        self::assertCount(2, $orContains->rows());

        $tagsOnly = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'select' => ['tags'],
            'filter' => ['=title' => 'one'],
        ]));
        self::assertSame(['a', 'b'], $tagsOnly->rows()[0]['tags']);
        self::assertArrayNotHasKey('id', $tagsOnly->rows()[0]);

        $noTags = $table->getList(ListQuery::fromOptions([
            'limit' => 10,
            'select' => ['id', 'title'],
            'filter' => ['=title' => 'one'],
        ]));
        self::assertArrayNotHasKey('tags', $noTags->rows()[0]);
    }

    /**
     * updateTable создаёт отсутствующий sidecar.
     *
     * @return void
     */
    public function testUpdateTableCreatesMfv(): void
    {
        $table = $this->gateway()->open(MultipleProbeTable::class);
        $table->createTable();
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        $schemaBuilder->dropIfExists('st_mfv_probe_mfv_tags');
        $table->updateTable();
        self::assertTrue($schemaBuilder->hasTable('st_mfv_probe_mfv_tags'));
        self::assertFalse($schemaBuilder->hasColumn('st_mfv_probe', 'tags'));
    }

    /**
     * Int multiple: DDL, порядок get и равенство скаляра.
     *
     * @return void
     */
    public function testIntMultiple(): void
    {
        $table = $this->gateway()->open(IntMultipleTable::class);
        $table->createTable();
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        self::assertTrue($schemaBuilder->hasTable('st_mfv_ints_mfv_codes'));
        self::assertFalse($schemaBuilder->hasColumn('st_mfv_ints', 'codes'));

        $rowId = $table->add(['codes' => [2, 1]]);
        self::assertSame([1, 2], $table->get($rowId)['codes']);
        $table->add(['codes' => [1]]);
        $exact = $table->getList(ListQuery::fromOptions(['limit' => 10, 'filter' => ['codes' => 1]]));
        self::assertCount(1, $exact->rows());
        self::assertSame([1], $exact->rows()[0]['codes']);
    }

    /**
     * Открывает probe с таблицей.
     *
     * @return IOpenedTable Таблица.
     */
    private function openProbe(): IOpenedTable
    {
        $table = $this->gateway()->open(MultipleProbeTable::class);
        $table->createTable();

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

        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $schemaBuilder->dropIfExists('st_mfv_probe_mfv_tags');
        $schemaBuilder->dropIfExists('st_mfv_required_mfv_tags');
        $schemaBuilder->dropIfExists('st_mfv_ints_mfv_codes');
        $schemaBuilder->dropIfExists('st_mfv_probe');
        $schemaBuilder->dropIfExists('st_mfv_required');
        $schemaBuilder->dropIfExists('st_mfv_ints');
    }

    /**
     * Проверяет sidecar без колонки tags.
     *
     * @return void
     */
    private function assertProbeStorage(): void
    {
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        self::assertTrue($schemaBuilder->hasTable('st_mfv_probe_mfv_tags'));
        self::assertFalse($schemaBuilder->hasColumn('st_mfv_probe', 'tags'));
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
