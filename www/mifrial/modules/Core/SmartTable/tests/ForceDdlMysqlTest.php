<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Illuminate\Database\Schema\Builder;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceChildBareTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceChildTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceFlipIndexedTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceFlipUniqueTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceIndexedTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceMfvScalarTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceMfvTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceParentTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForcePlainAgeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForcePlainTitleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceRelNoneTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceRelRestrictTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceSelfTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ForceUniqueTable;
use Mifrial\Core\SmartTable\Tests\Fixture\MiniTitleNoteTable;
use Mifrial\Core\SmartTable\Tests\Fixture\MiniTitleTable;
use PHPUnit\Framework\TestCase;

final class ForceDdlMysqlTest extends TestCase
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
     * force снимает leftover колонку, updateTable — нет.
     *
     * @return void
     */
    public function testForceDropsLeftoverColumnUpdateKeepsIt(): void
    {
        $this->gateway()->open(MiniTitleNoteTable::class)->createTable();
        $this->gateway()->open(MiniTitleTable::class)->updateTable();
        $schemaBuilder = $this->schema();
        self::assertContains('note', $schemaBuilder->getColumnListing('st_crud_mini'));
        $this->gateway()->open(MiniTitleTable::class)->forceUpdateTable();
        self::assertNotContains('note', $schemaBuilder->getColumnListing('st_crud_mini'));
        self::assertContains('title', $schemaBuilder->getColumnListing('st_crud_mini'));
    }

    /**
     * force снимает leftover unique.
     *
     * @return void
     */
    public function testForceDropsLeftoverUnique(): void
    {
        $this->gateway()->open(ForceUniqueTable::class)->createTable();
        $this->gateway()->open(ForcePlainTitleTable::class)->forceUpdateTable();
        self::assertNotContains('st_force_unq_title_unq', $this->indexNames('st_force_unq'));
        $plainTable = $this->gateway()->open(ForcePlainTitleTable::class);
        self::assertSame(1, $plainTable->add(['title' => 'same']));
        self::assertSame(2, $plainTable->add(['title' => 'same']));
    }

    /**
     * force снимает leftover index.
     *
     * @return void
     */
    public function testForceDropsLeftoverIndex(): void
    {
        $this->gateway()->open(ForceIndexedTable::class)->createTable();
        $this->gateway()->open(ForcePlainAgeTable::class)->forceUpdateTable();
        self::assertNotContains('st_force_idx_age_idx', $this->indexNames('st_force_idx'));
    }

    /**
     * unique ↔ indexed: один leftover снят, нужный создан.
     *
     * @return void
     */
    public function testForceFlipsUniqueAndIndexed(): void
    {
        $this->gateway()->open(ForceFlipUniqueTable::class)->createTable();
        $this->gateway()->open(ForceFlipIndexedTable::class)->forceUpdateTable();
        $afterIndexed = $this->indexNames('st_force_flip');
        self::assertContains('st_force_flip_title_idx', $afterIndexed);
        self::assertNotContains('st_force_flip_title_unq', $afterIndexed);
        $this->gateway()->open(ForceFlipUniqueTable::class)->forceUpdateTable();
        $afterUnique = $this->indexNames('st_force_flip');
        self::assertContains('st_force_flip_title_unq', $afterUnique);
        self::assertNotContains('st_force_flip_title_idx', $afterUnique);
    }

    /**
     * force снимает leftover mfv при снятом multiple.
     *
     * @return void
     */
    public function testForceDropsLeftoverMfv(): void
    {
        $this->gateway()->open(ForceMfvTable::class)->createTable();
        self::assertTrue($this->schema()->hasTable('st_force_mfv_mfv_tags'));
        $this->gateway()->open(ForceMfvScalarTable::class)->forceUpdateTable();
        self::assertFalse($this->schema()->hasTable('st_force_mfv_mfv_tags'));
        self::assertContains('tags', $this->schema()->getColumnListing('st_force_mfv'));
    }

    /**
     * force снимает leftover колонку и FK.
     *
     * @return void
     */
    public function testForceDropsLeftoverReference(): void
    {
        $this->gateway()->open(ForceParentTable::class)->createTable();
        $this->gateway()->open(ForceChildTable::class)->createTable();
        $this->gateway()->open(ForceChildBareTable::class)->forceUpdateTable();
        $schemaBuilder = $this->schema();
        self::assertNotContains('parent_id', $schemaBuilder->getColumnListing('st_force_child'));
        self::assertSame([], $schemaBuilder->getForeignKeys('st_force_child'));
        self::assertTrue($schemaBuilder->hasTable('st_force_parent'));
    }

    /**
     * restrict → none: колонка остаётся, FK нет.
     *
     * @return void
     */
    public function testForceDropsFkWhenOnDeleteNone(): void
    {
        $this->gateway()->open(ForceParentTable::class)->createTable();
        $this->gateway()->open(ForceRelRestrictTable::class)->createTable();
        $this->gateway()->open(ForceRelNoneTable::class)->forceUpdateTable();
        $schemaBuilder = $this->schema();
        self::assertContains('parent_id', $schemaBuilder->getColumnListing('st_force_rel'));
        self::assertSame([], $schemaBuilder->getForeignKeys('st_force_rel'));
    }

    /**
     * deleteTable снимает стол и mfv карты.
     *
     * @return void
     */
    public function testDeleteTableDropsMainAndMappedMfv(): void
    {
        $this->gateway()->open(ForceMfvTable::class)->createTable();
        $this->gateway()->open(ForceMfvTable::class)->deleteTable();
        $schemaBuilder = $this->schema();
        self::assertFalse($schemaBuilder->hasTable('st_force_mfv'));
        self::assertFalse($schemaBuilder->hasTable('st_force_mfv_mfv_tags'));
        try {
            $this->gateway()->open(ForceMfvTable::class)->deleteTable();
            self::fail('second deleteTable must fail');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }
    }

    /**
     * deleteTable self-ref проходит.
     *
     * @return void
     */
    public function testDeleteSelfRefTable(): void
    {
        $this->gateway()->open(ForceSelfTable::class)->createTable();
        $this->gateway()->open(ForceSelfTable::class)->deleteTable();
        self::assertFalse($this->schema()->hasTable('st_force_self'));
    }

    /**
     * delete parent при живом child — DDL_FAILED.
     *
     * @return void
     */
    public function testDeleteParentWithChildFails(): void
    {
        $parentTable = $this->gateway()->open(ForceParentTable::class);
        $parentTable->createTable();
        $this->gateway()->open(ForceChildTable::class)->createTable();
        $parentId = $parentTable->add(['title' => 'p']);
        $this->gateway()->open(ForceChildTable::class)->add(['parent_id' => $parentId]);
        try {
            $parentTable->deleteTable();
            self::fail('delete parent with child must fail');
        } catch (DdlFailedException $exception) {
            self::assertSame('DDL_FAILED', $exception->getErrorCode());
        }

        self::assertTrue($this->schema()->hasTable('st_force_child'));
        self::assertTrue($this->schema()->hasTable('st_force_parent'));
    }

    /**
     * Нет таблицы → TABLE_MISSING.
     *
     * @return void
     */
    public function testMissingTableErrors(): void
    {
        try {
            $this->gateway()->open(MiniTitleTable::class)->forceUpdateTable();
            self::fail('force without table must fail');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
        }

        try {
            $this->gateway()->open(MiniTitleTable::class)->deleteTable();
            self::fail('delete without table must fail');
        } catch (TableMissingException $exception) {
            self::assertSame('TABLE_MISSING', $exception->getErrorCode());
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

        $schemaBuilder = $this->databaseConnection->illuminateConnection()->getSchemaBuilder();
        $schemaBuilder->dropIfExists('st_force_child');
        $schemaBuilder->dropIfExists('st_force_rel');
        $schemaBuilder->dropIfExists('st_force_self');
        $schemaBuilder->dropIfExists('st_force_parent');
        $schemaBuilder->dropIfExists('st_force_mfv_mfv_tags');
        $schemaBuilder->dropIfExists('st_force_mfv');
        $schemaBuilder->dropIfExists('st_force_unq');
        $schemaBuilder->dropIfExists('st_force_idx');
        $schemaBuilder->dropIfExists('st_force_flip');
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
