<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\Catalog\SmartTableCatalog;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\Query\CatalogDefinitionLookup;
use Mifrial\Core\SmartTable\Service\Query\FieldPathWalker;
use Mifrial\Core\SmartTable\Service\Query\ListFilterBinder;
use Mifrial\Core\SmartTable\Service\Query\ListQueryCompiler;
use Mifrial\Core\SmartTable\Service\Query\MfvRows;
use Mifrial\Core\SmartTable\Service\Query\RowAssembler;
use Mifrial\Core\SmartTable\Service\Query\TableList;
use Mifrial\Core\SmartTable\Service\Query\TableRows;
use Mifrial\Core\SmartTable\Service\Schema\MfvSchema;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use WeakMap;

/**
 * Сборка шлюза и каталога на одном адаптере соединения и кэше.
 */
final class SmartTableSupport
{
    /**
     * @var WeakMap<IlluminateDatabaseConnection, TableCache>|null
     */
    private static ?WeakMap $tableCaches = null;

    /**
     * Создаёт сборщик.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param CacheSettings $cacheSettings Срез кэша.
     * @param bool $debug Режим исключений I/O кэша.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly CacheSettings $cacheSettings,
        private readonly bool $debug,
    ) {
    }

    /**
     * Собирает шлюз таблиц с PHP-классом.
     *
     * @return SmartTableGateway Шлюз.
     */
    public function makeGateway(): SmartTableGateway
    {
        return new SmartTableGateway(
            $this->databaseConnection,
            $this->tableSchema(),
            $this->tableRows(),
            $this->tableList(new CatalogDefinitionLookup()),
            $this->tableCache(),
        );
    }

    /**
     * Собирает каталог словаря.
     *
     * @return SmartTableCatalog Каталог.
     */
    public function makeCatalog(): SmartTableCatalog
    {
        $catalogLookup = new CatalogDefinitionLookup();
        $tableList = $this->tableList($catalogLookup);
        $catalog = new SmartTableCatalog(
            $this->tableSchema(),
            $this->tableRows(),
            $tableList,
            new MfvSchema($this->databaseConnection),
            $this->tableCache(),
        );
        $catalogLookup->attach(
            static function (string $tableName) use ($catalog): SmartTableDefinition {
                return $catalog->definitionByName($tableName);
            },
        );

        return $catalog;
    }

    /**
     * Один TableCache на объект соединения.
     *
     * @return TableCache Кэш.
     */
    private function tableCache(): TableCache
    {
        self::$tableCaches ??= new WeakMap();
        if (isset(self::$tableCaches[$this->databaseConnection])) {
            return self::$tableCaches[$this->databaseConnection];
        }

        $databaseConnection = $this->databaseConnection;
        $tableCache = new TableCache(
            $this->cacheSettings,
            $this->debug,
            static function () use ($databaseConnection): int {
                return $databaseConnection->illuminateConnection()->transactionLevel();
            },
        );
        self::$tableCaches[$this->databaseConnection] = $tableCache;

        return $tableCache;
    }

    /**
     * DDL-оркестратор.
     *
     * @return TableSchema Схема.
     */
    private function tableSchema(): TableSchema
    {
        return new TableSchema($this->databaseConnection);
    }

    /**
     * Строки таблицы.
     *
     * @return TableRows Строки.
     */
    private function tableRows(): TableRows
    {
        $driverErrors = new DriverErrorTranslator();

        return new TableRows(
            $this->databaseConnection,
            new RowAssembler(),
            $driverErrors,
            new MfvRows($this->databaseConnection, $driverErrors),
        );
    }

    /**
     * Список таблицы.
     *
     * @param CatalogDefinitionLookup $catalogLookup Карты словаря для hop.
     *
     * @return TableList Список.
     */
    private function tableList(CatalogDefinitionLookup $catalogLookup): TableList
    {
        $driverErrors = new DriverErrorTranslator();
        $mfvRows = new MfvRows($this->databaseConnection, $driverErrors);
        $fieldPathWalker = new FieldPathWalker($catalogLookup);

        return new TableList(
            $this->databaseConnection,
            new RowAssembler(),
            $driverErrors,
            new ListQueryCompiler(new ListFilterBinder(), $fieldPathWalker),
            $mfvRows,
            $fieldPathWalker,
        );
    }
}
