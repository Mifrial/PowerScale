<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Schema\TableExistsException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Exception\Transaction\TransactionOpenException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\CrudProbeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\MiniTitleNoteTable;
use Mifrial\Core\SmartTable\Tests\Fixture\MiniTitleTable;
use PHPUnit\Framework\TestCase;

final class CrudMysqlTest extends TestCase
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
     * Проверяет create, add, get по скалярным типам.
     *
     * @return void
     */
    public function testRoundTrip(): void
    {
        $table = $this->gateway()->open(CrudProbeTable::class);
        $table->schema()->createTable();
        $created = UnixDateTime::fromUnix(1700000000);
        $rowId = $table->records()->add([
            'title' => 'hello',
            'body' => 'text',
            'html' => '<b>',
            'age' => 10,
            'active' => true,
            'created' => $created,
            'payload' => ['a' => 1],
        ]);
        $row = $table->records()->getById($rowId);
        self::assertNotNull($row);
        self::assertSame($rowId, $row['id']);
        self::assertSame('hello', $row['title']);
        self::assertSame('text', $row['body']);
        self::assertSame('<b>', $row['html']);
        self::assertSame(10, $row['age']);
        self::assertTrue($row['active']);
        self::assertSame(1700000000, $row['created']->toUnix());
        self::assertSame(['a' => 1], $row['payload']);
    }

    /**
     * Проверяет частичный update, delete и get unknown.
     *
     * @return void
     */
    public function testUpdateDeleteAndMissingGet(): void
    {
        $table = $this->gateway()->open(CrudProbeTable::class);
        $table->schema()->createTable();
        $rowId = $table->records()->add(['title' => 'a']);
        $table->records()->update($rowId, ['title' => 'b']);
        $row = $table->records()->getById($rowId);
        self::assertNotNull($row);
        self::assertSame('b', $row['title']);
        $table->records()->delete($rowId);
        self::assertNull($table->records()->getById($rowId));
        self::assertNull($table->records()->getById(999999));
        try {
            $table->records()->update($rowId, ['title' => 'c']);
            self::fail('update missing row must fail');
        } catch (RowNotFoundException $exception) {
            self::assertSame('ROW_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $table->records()->delete($rowId);
            self::fail('delete missing row must fail');
        } catch (RowNotFoundException $exception) {
            self::assertSame('ROW_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет required и неизвестный ключ.
     *
     * @return void
     */
    public function testAddValidation(): void
    {
        $table = $this->gateway()->open(CrudProbeTable::class);
        $table->schema()->createTable();
        try {
            $table->records()->add([]);
            self::fail('required title must fail');
        } catch (FieldRequiredException $exception) {
            self::assertSame('FIELD_REQUIRED', $exception->getErrorCode());
        }

        try {
            $table->records()->add(['title' => 'a', 'nope' => 1]);
            self::fail('unknown key must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет повторный create и CRUD без таблицы.
     *
     * @return void
     */
    public function testCreateTwiceAndMissingTable(): void
    {
        $table = $this->gateway()->open(CrudProbeTable::class);
        self::assertFalse($table->schema()->exists());
        $table->schema()->createTable();
        self::assertTrue($table->schema()->exists());
        try {
            $table->schema()->createTable();
            self::fail('second create must fail');
        } catch (TableExistsException $exception) {
            self::assertSame('TABLE_EXISTS', $exception->getErrorCode());
        }

        $this->dropFixtureTables();
        self::assertFalse($table->schema()->exists());
        try {
            $table->records()->getById(1);
            self::fail('missing table must fail');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет, что updateTable добавляет nullable колонку.
     *
     * @return void
     */
    public function testUpdateTableAddsColumn(): void
    {
        $this->gateway()->open(MiniTitleTable::class)->schema()->createTable();
        $extended = $this->gateway()->open(MiniTitleNoteTable::class);
        $extended->schema()->updateTable();
        $rowId = $extended->records()->add(['title' => 'a', 'note' => 'n']);
        $row = $extended->records()->getById($rowId);
        self::assertNotNull($row);
        self::assertSame('n', $row['note']);
    }

    /**
     * Проверяет rollback add и вложенную транзакцию.
     *
     * @return void
     */
    public function testTransactionRollbackAndNested(): void
    {
        $gateway = $this->gateway();
        $table = $gateway->open(CrudProbeTable::class);
        $table->schema()->createTable();
        $rowId = 0;
        try {
            $gateway->transaction(function () use ($table, &$rowId): void {
                $rowId = $table->records()->add(['title' => 'tx']);
                throw new MapInvalidException('rollback probe');
            });
            self::fail('transaction must rethrow');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        self::assertNull($table->records()->getById($rowId));

        try {
            $gateway->transaction(function () use ($gateway): void {
                $gateway->transaction(static function (): void {
                });
            });
            self::fail('nested transaction must fail');
        } catch (TransactionOpenException $exception) {
            self::assertSame('TRANSACTION_OPEN', $exception->getErrorCode());
        }
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
        $schemaBuilder->dropIfExists('st_crud_probe');
        $schemaBuilder->dropIfExists('st_crud_mini');
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
