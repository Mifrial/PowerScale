<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\ChildNoneTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ChildRestrictTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ChildSetNullTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ParentRefTable;
use Mifrial\Core\SmartTable\Tests\Fixture\SelfRefTable;
use PHPUnit\Framework\TestCase;

final class ReferenceMysqlTest extends TestCase
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
     * Удаляет фикстурные таблицы: child, потом parent.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropFixtureTables();
    }

    /**
     * CREATE parent/child, signed INT и имя FK.
     *
     * @return void
     */
    public function testCreateColumnAndConstraint(): void
    {
        $parent = $this->openParent();
        $child = $this->openChild();
        $parent->createTable();
        $child->createTable();
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        $column = null;
        foreach ($schemaBuilder->getColumns('st_ref_child') as $columnMeta) {
            if (($columnMeta['name'] ?? '') === 'parent_id') {
                $column = $columnMeta;
                break;
            }
        }
        self::assertIsArray($column);
        self::assertFalse((bool) ($column['unsigned'] ?? false));
        $constraintNames = [];
        foreach ($schemaBuilder->getForeignKeys('st_ref_child') as $foreignKey) {
            if (isset($foreignKey['name']) && is_string($foreignKey['name'])) {
                $constraintNames[] = $foreignKey['name'];
            }
        }
        self::assertContains('st_ref_child_parent_id_fk', $constraintNames);
    }

    /**
     * CREATE child без parent — TABLE_MISSING.
     *
     * @return void
     */
    public function testCreateChildWithoutParent(): void
    {
        try {
            $this->openChild()->createTable();
            self::fail('child without parent table must fail');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }

        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        self::assertFalse($schemaBuilder->hasTable('st_ref_child'));
    }

    /**
     * add/get живого id; add и update на мёртвый id — REFERENCE_CONSTRAINT.
     *
     * @return void
     */
    public function testAddUpdateMissingParent(): void
    {
        $parent = $this->openParent();
        $child = $this->openChild();
        $parent->createTable();
        $child->createTable();
        $parentId = $parent->add(['title' => 'p']);
        $childId = $child->add(['parent_id' => $parentId]);
        $row = $child->getById($childId);
        self::assertIsArray($row);
        self::assertSame($parentId, $row['parent_id']);

        try {
            $child->add(['parent_id' => $parentId + 100]);
            self::fail('add missing parent must fail');
        } catch (ReferenceConstraintException $exception) {
            self::assertSame('REFERENCE_CONSTRAINT', $exception->getErrorCode());
        }

        try {
            $child->update($childId, ['parent_id' => $parentId + 100]);
            self::fail('update missing parent must fail');
        } catch (ReferenceConstraintException $exception) {
            self::assertSame('REFERENCE_CONSTRAINT', $exception->getErrorCode());
        }
    }

    /**
     * Restrict: delete parent при child отказывает; child потом parent — ок.
     *
     * @return void
     */
    public function testRestrictDelete(): void
    {
        $parent = $this->openParent();
        $child = $this->openChild();
        $parent->createTable();
        $child->createTable();
        $parentId = $parent->add(['title' => 'p']);
        $childId = $child->add(['parent_id' => $parentId]);
        try {
            $parent->delete($parentId);
            self::fail('delete parent with child must fail');
        } catch (ReferenceConstraintException $exception) {
            self::assertSame('REFERENCE_CONSTRAINT', $exception->getErrorCode());
        }
        self::assertNotNull($child->getById($childId));
        $child->delete($childId);
        $parent->delete($parentId);
        self::assertNull($parent->getById($parentId));
    }

    /**
     * setNull обнуляет; none оставляет висячий id.
     *
     * @return void
     */
    public function testSetNullAndNone(): void
    {
        $parent = $this->openParent();
        $setNull = $this->gateway()->open(ChildSetNullTable::class);
        $none = $this->gateway()->open(ChildNoneTable::class);
        $parent->createTable();
        $setNull->createTable();
        $none->createTable();
        $parentId = $parent->add(['title' => 'p']);
        $setNullId = $setNull->add(['parent_id' => $parentId]);
        $noneId = $none->add(['parent_id' => $parentId]);
        $parent->delete($parentId);
        self::assertNull($setNull->getById($setNullId)['parent_id']);
        self::assertSame($parentId, $none->getById($noneId)['parent_id']);
        self::assertNull($parent->getById($parentId));
    }

    /**
     * updateTable ставит недостающий FK, колонку не трогает.
     *
     * @return void
     */
    public function testUpdateTableRestoresForeignKey(): void
    {
        $parent = $this->openParent();
        $child = $this->openChild();
        $parent->createTable();
        $child->createTable();
        $schemaBuilder = $this->databaseConnection?->illuminateConnection()->getSchemaBuilder();
        self::assertNotNull($schemaBuilder);
        $schemaBuilder->table('st_ref_child', function (Blueprint $blueprint): void {
            $blueprint->dropForeign('st_ref_child_parent_id_fk');
        });
        self::assertTrue($schemaBuilder->hasColumn('st_ref_child', 'parent_id'));
        $child->updateTable();
        $constraintNames = [];
        foreach ($schemaBuilder->getForeignKeys('st_ref_child') as $foreignKey) {
            if (isset($foreignKey['name']) && is_string($foreignKey['name'])) {
                $constraintNames[] = $foreignKey['name'];
            }
        }
        self::assertContains('st_ref_child_parent_id_fk', $constraintNames);
    }

    /**
     * getList IN и sort по parent_id.
     *
     * @return void
     */
    public function testGetListInAndSort(): void
    {
        $parent = $this->openParent();
        $child = $this->openChild();
        $parent->createTable();
        $child->createTable();
        $firstParent = $parent->add(['title' => 'a']);
        $secondParent = $parent->add(['title' => 'b']);
        $child->add(['parent_id' => $secondParent]);
        $child->add(['parent_id' => $firstParent]);
        $listResult = $child->getList(ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['parent_id' => [$firstParent, $secondParent]],
            'sort' => ['parent_id' => 'asc'],
        ]));
        self::assertCount(2, $listResult->rows());
        self::assertSame($firstParent, $listResult->rows()[0]['parent_id']);
        self::assertSame($secondParent, $listResult->rows()[1]['parent_id']);
    }

    /**
     * Self-ref: две строки A→B; delete B отказ; A затем B — ок.
     *
     * @return void
     */
    public function testSelfReference(): void
    {
        $table = $this->gateway()->open(SelfRefTable::class);
        $table->createTable();
        $rowB = $table->add([]);
        $rowA = $table->add(['parent_id' => $rowB]);
        try {
            $table->delete($rowB);
            self::fail('delete referenced self-row must fail');
        } catch (ReferenceConstraintException $exception) {
            self::assertSame('REFERENCE_CONSTRAINT', $exception->getErrorCode());
        }
        $table->delete($rowA);
        $table->delete($rowB);
        self::assertNull($table->getById($rowB));
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
     * Открывает parent.
     *
     * @return IOpenedTable Таблица.
     */
    private function openParent(): IOpenedTable
    {
        return $this->gateway()->open(ParentRefTable::class);
    }

    /**
     * Открывает restrict child.
     *
     * @return IOpenedTable Таблица.
     */
    private function openChild(): IOpenedTable
    {
        return $this->gateway()->open(ChildRestrictTable::class);
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
        $schemaBuilder->dropIfExists('st_ref_child');
        $schemaBuilder->dropIfExists('st_ref_setnull');
        $schemaBuilder->dropIfExists('st_ref_none');
        $schemaBuilder->dropIfExists('st_ref_self');
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
