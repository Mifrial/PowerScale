<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\BigIdMfvTable;
use Mifrial\Core\SmartTable\Tests\Fixture\BigIdTable;
use PHPUnit\Framework\TestCase;

/**
 * BIGINT id, колонка и mfv owner_id.
 */
final class BigIntMysqlTest extends TestCase
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
     * CREATE signed BIGINT, add/get, filter range.
     *
     * @return void
     */
    public function testWideIdAndScore(): void
    {
        $table = $this->gateway()->open(BigIdTable::class);
        $table->schema()->createTable();
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        $idColumn = $this->columnMeta('st_big_id', 'id');
        $scoreColumn = $this->columnMeta('st_big_id', 'score');
        self::assertIsArray($idColumn);
        self::assertIsArray($scoreColumn);
        self::assertStringContainsString('bigint', strtolower((string) ($idColumn['type'] ?? '')));
        self::assertFalse((bool) ($idColumn['unsigned'] ?? false));
        self::assertStringContainsString('bigint', strtolower((string) ($scoreColumn['type'] ?? '')));

        $firstId = $table->records()->add(['score' => 5]);
        $secondId = $table->records()->add(['score' => 20]);
        self::assertIsInt($firstId);
        $row = $table->records()->getById($firstId);
        self::assertIsArray($row);
        self::assertSame(5, $row['score']);
        $filtered = $table->records()->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['<score' => 10],
        ]));
        self::assertCount(1, $filtered->rows());
        self::assertSame($firstId, $filtered->rows()[0]['id']);
        self::assertNotSame($secondId, $filtered->rows()[0]['id']);
    }

    /**
     * mfv owner_id и value — BIGINT при широком id и multiple bigint.
     *
     * @return void
     */
    public function testMfvOwnerMatchesWideId(): void
    {
        $table = $this->gateway()->open(BigIdMfvTable::class);
        $table->schema()->createTable();
        $rowId = $table->records()->add(['codes' => [8, 3]]);
        $row = $table->records()->getById($rowId);
        self::assertIsArray($row);
        self::assertSame([3, 8], $row['codes']);
        $ownerColumn = $this->columnMeta('st_big_mfv_mfv_codes', 'owner_id');
        $valueColumn = $this->columnMeta('st_big_mfv_mfv_codes', 'value');
        self::assertIsArray($ownerColumn);
        self::assertIsArray($valueColumn);
        self::assertStringContainsString('bigint', strtolower((string) ($ownerColumn['type'] ?? '')));
        self::assertStringContainsString('bigint', strtolower((string) ($valueColumn['type'] ?? '')));
    }

    /**
     * Метаданные колонки или null.
     *
     * @param string $tableName Таблица.
     * @param string $columnName Колонка.
     *
     * @return array<string, mixed>|null Строка schema.
     */
    private function columnMeta(string $tableName, string $columnName): ?array
    {
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        foreach ($schemaBuilder->getColumns($tableName) as $columnMeta) {
            if (($columnMeta['name'] ?? '') === $columnName && is_array($columnMeta)) {
                return $columnMeta;
            }
        }

        return null;
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
        $schemaBuilder->dropIfExists('st_big_mfv_mfv_codes');
        $schemaBuilder->dropIfExists('st_big_mfv');
        $schemaBuilder->dropIfExists('st_big_id');
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
