<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Illuminate\Database\Schema\Builder;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\TableExistsException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Interface\Service\ITableCatalog;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Table\MetaTableDefinition;
use Mifrial\Core\SmartTable\Table\RuntimeDefinition;
use Mifrial\Core\SmartTable\Tests\Fixture\MiniTitleTable;
use PHPUnit\Framework\TestCase;

final class DictionaryMysqlTest extends TestCase
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
     * installMeta дважды не ломает meta.
     *
     * @return void
     */
    public function testInstallMetaTwice(): void
    {
        $this->catalog()->installMeta();
        self::assertTrue($this->schema()->hasTable('st_meta_table'));
        self::assertTrue($this->schema()->hasTable('st_meta_field'));
    }

    /**
     * create, openByName, add/get строки.
     *
     * @return void
     */
    public function testCreateOpenAddGet(): void
    {
        $openedTable = $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'title', 'type' => 'string', 'required' => true],
        ]);
        $rowId = $openedTable->add(['title' => 'hello']);
        $row = $this->catalog()->openByName('st_dict_probe')->getById($rowId);
        self::assertIsArray($row);
        self::assertSame('hello', $row['title']);
    }

    /**
     * addField виден в get; dropField скаляра снимает колонку.
     *
     * @return void
     */
    public function testAddAndDropScalarField(): void
    {
        $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'title', 'type' => 'string', 'required' => true],
        ]);
        $this->catalog()->addField('st_dict_probe', ['name' => 'note', 'type' => 'string']);
        $rowId = $this->catalog()->openByName('st_dict_probe')->add(['title' => 'a', 'note' => 'b']);
        $row = $this->catalog()->openByName('st_dict_probe')->getById($rowId);
        self::assertIsArray($row);
        self::assertSame('b', $row['note']);
        $this->catalog()->dropField('st_dict_probe', 'note');
        self::assertNotContains('note', $this->schema()->getColumnListing('st_dict_probe'));
    }

    /**
     * dropField multiple снимает sidecar.
     *
     * @return void
     */
    public function testDropMultipleField(): void
    {
        $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'tags', 'type' => 'int', 'multiple' => true],
        ]);
        self::assertTrue($this->schema()->hasTable('st_dict_probe_mfv_tags'));
        $this->catalog()->dropField('st_dict_probe', 'tags');
        self::assertFalse($this->schema()->hasTable('st_dict_probe_mfv_tags'));
    }

    /**
     * maxLength задаёт VARCHAR.
     *
     * @return void
     */
    public function testStringMaxLength(): void
    {
        $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'title', 'type' => 'string', 'maxLength' => 32],
        ]);
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);
        $columnRow = $this->databaseConnection->illuminateConnection()->selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['st_dict_probe', 'title'],
        );
        self::assertIsObject($columnRow);
        self::assertSame(32, (int) $columnRow->len);
    }

    /**
     * Неизвестный type и hydrator — MAP_INVALID.
     *
     * @return void
     */
    public function testInvalidSpecs(): void
    {
        try {
            $this->catalog()->createTable('st_dict_probe', [['name' => 'x', 'type' => 'uuid']]);
            self::fail('unknown type');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $this->catalog()->createTable('st_dict_probe', [
                ['name' => 'payload', 'type' => 'json', 'hydrator' => 'x'],
            ]);
            self::fail('hydrator');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустой fieldSpecs — только id.
     *
     * @return void
     */
    public function testEmptyFieldSpecs(): void
    {
        $openedTable = $this->catalog()->createTable('st_dict_empty', []);
        $rowId = $openedTable->add([]);
        $row = $openedTable->getById($rowId);
        self::assertIsArray($row);
        self::assertSame($rowId, $row['id']);
    }

    /**
     * Невалидное имя и имена meta — MAP_INVALID.
     *
     * @return void
     */
    public function testInvalidAndReservedNames(): void
    {
        try {
            $this->catalog()->createTable('StBad', []);
            self::fail('invalid name');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $this->catalog()->createTable('st_meta_table', []);
            self::fail('reserved');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * addField без таблицы — TABLE_MISSING.
     *
     * @return void
     */
    public function testAddFieldUnknownTable(): void
    {
        try {
            $this->catalog()->addField('st_dict_missing', ['name' => 'title', 'type' => 'string']);
            self::fail('missing');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }
    }

    /**
     * dropTable снимает физику и словарь.
     *
     * @return void
     */
    public function testDropTableRemovesPhysicsAndDict(): void
    {
        $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'tags', 'type' => 'int', 'multiple' => true],
        ]);
        $this->catalog()->dropTable('st_dict_probe');
        self::assertFalse($this->schema()->hasTable('st_dict_probe'));
        self::assertFalse($this->schema()->hasTable('st_dict_probe_mfv_tags'));
        try {
            $this->catalog()->openByName('st_dict_probe');
            self::fail('open after drop');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }
    }

    /**
     * Дубль имени таблицы в meta — UNIQUE; дубль поля — MAP_INVALID.
     *
     * @return void
     */
    public function testDuplicateNames(): void
    {
        $this->gateway()->open(MetaTableDefinition::class)->add(['name' => 'st_dict_dup']);
        try {
            $this->gateway()->open(MetaTableDefinition::class)->add(['name' => 'st_dict_dup']);
            self::fail('unique');
        } catch (UniqueConstraintException $exception) {
            self::assertSame('UNIQUE_CONSTRAINT', $exception->getErrorCode());
        }

        $this->catalog()->createTable('st_dict_probe', [
            ['name' => 'title', 'type' => 'string'],
        ]);
        try {
            $this->catalog()->addField('st_dict_probe', ['name' => 'title', 'type' => 'string']);
            self::fail('dup field');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Каталог не занимает имя живой таблицы с PHP-классом.
     *
     * @return void
     */
    public function testCreateAgainstCodedTable(): void
    {
        $this->gateway()->open(MiniTitleTable::class)->createTable();
        try {
            $this->catalog()->createTable('st_crud_mini', [
                ['name' => 'title', 'type' => 'string'],
            ]);
            self::fail('exists');
        } catch (TableExistsException $exception) {
            self::assertSame('TABLE_EXISTS', $exception->getErrorCode());
        }
    }

    /**
     * Сирота meta без физики: повтор create поднимает стол; drop без физики чистит словарь.
     *
     * @return void
     */
    public function testOrphanMeta(): void
    {
        $this->gateway()->open(MetaTableDefinition::class)->add(['name' => 'st_dict_orphan']);
        self::assertFalse($this->schema()->hasTable('st_dict_orphan'));
        $this->catalog()->createTable('st_dict_orphan', []);
        self::assertTrue($this->schema()->hasTable('st_dict_orphan'));
        $this->schema()->dropIfExists('st_dict_orphan');
        $this->catalog()->dropTable('st_dict_orphan');
        try {
            $this->catalog()->openByName('st_dict_orphan');
            self::fail('dropped orphan');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }
    }

    /**
     * open RuntimeDefinition через gateway — MAP_INVALID.
     *
     * @return void
     */
    public function testGatewayRejectsRuntimeDefinition(): void
    {
        try {
            $this->gateway()->open(RuntimeDefinition::class);
            self::fail('runtime class');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Reference runtime→runtime и runtime→таблица с PHP-классом.
     *
     * @return void
     */
    public function testRuntimeReferences(): void
    {
        $this->catalog()->createTable('st_dict_parent', [
            ['name' => 'title', 'type' => 'string', 'required' => true],
        ]);
        $this->catalog()->createTable('st_dict_child', [
            [
                'name' => 'parent_id',
                'type' => 'reference',
                'required' => true,
                'target' => 'st_dict_parent',
            ],
        ]);
        $parentId = $this->catalog()->openByName('st_dict_parent')->add(['title' => 'p']);
        $this->catalog()->openByName('st_dict_child')->add(['parent_id' => $parentId]);

        $this->gateway()->open(MiniTitleTable::class)->createTable();
        $this->catalog()->createTable('st_dict_probe', [
            [
                'name' => 'mini_id',
                'type' => 'reference',
                'target' => 'st_crud_mini',
            ],
        ]);
        self::assertTrue($this->schema()->hasTable('st_dict_probe'));
    }

    /**
     * dropTable parent при живом child — DDL_FAILED, словарь parent на месте.
     *
     * @return void
     */
    public function testDropParentWithChildFails(): void
    {
        $this->catalog()->createTable('st_dict_parent', []);
        $this->catalog()->createTable('st_dict_child', [
            [
                'name' => 'parent_id',
                'type' => 'reference',
                'required' => true,
                'target' => 'st_dict_parent',
            ],
        ]);
        try {
            $this->catalog()->dropTable('st_dict_parent');
            self::fail('restrict');
        } catch (DdlFailedException $exception) {
            self::assertSame('DDL_FAILED', $exception->getErrorCode());
        }

        self::assertTrue($this->schema()->hasTable('st_dict_parent'));
        $this->catalog()->openByName('st_dict_parent');
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
     * Удаляет таблицы фикстур словаря.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->databaseConnection instanceof IlluminateDatabaseConnection) {
            return;
        }

        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $schemaBuilder->dropIfExists('st_dict_child');
        $schemaBuilder->dropIfExists('st_dict_parent');
        $schemaBuilder->dropIfExists('st_dict_probe_mfv_tags');
        $schemaBuilder->dropIfExists('st_dict_probe');
        $schemaBuilder->dropIfExists('st_dict_empty');
        $schemaBuilder->dropIfExists('st_dict_orphan');
        $schemaBuilder->dropIfExists('st_dict_dup');
        $schemaBuilder->dropIfExists('st_crud_mini');
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
