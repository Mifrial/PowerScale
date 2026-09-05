<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Cache\Service\FileCacheStore;
use Mifrial\Core\Cache\Service\UnusableCacheStore;
use Mifrial\Core\SmartTable\Service\Catalog\SmartTableCatalog;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\SmartTableGateway;
use Mifrial\Core\SmartTable\Service\SmartTableSupport;

/**
 * Сборка шлюза и каталога для тестов на одном адаптере.
 */
final class GatewayHarness
{
    /**
     * Собирает шлюз на переданном соединении.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param ICacheStore|null $cacheStore Кэш; непригодный по умолчанию.
     * @param bool $debug Исключения I/O кэша.
     *
     * @return SmartTableGateway Шлюз.
     */
    public static function make(
        IlluminateDatabaseConnection $databaseConnection,
        ?ICacheStore $cacheStore = null,
        bool $debug = true,
    ): SmartTableGateway {
        return (new SmartTableSupport(
            $databaseConnection,
            $cacheStore ?? new UnusableCacheStore(),
            $debug,
        ))->makeGateway();
    }

    /**
     * Собирает каталог на переданном соединении.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param ICacheStore|null $cacheStore Кэш; непригодный по умолчанию.
     * @param bool $debug Исключения I/O кэша.
     *
     * @return SmartTableCatalog Каталог.
     */
    public static function makeCatalog(
        IlluminateDatabaseConnection $databaseConnection,
        ?ICacheStore $cacheStore = null,
        bool $debug = true,
    ): SmartTableCatalog {
        return (new SmartTableSupport(
            $databaseConnection,
            $cacheStore ?? new UnusableCacheStore(),
            $debug,
        ))->makeCatalog();
    }

    /**
     * File-store во временном каталоге.
     *
     * @param string $basePath Каталог.
     *
     * @return FileCacheStore Store.
     */
    public static function fileStore(string $basePath): FileCacheStore
    {
        return new FileCacheStore($basePath);
    }
}
