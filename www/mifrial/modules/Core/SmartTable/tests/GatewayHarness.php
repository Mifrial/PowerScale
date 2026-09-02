<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Dto\CacheSettings;
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
     * @param CacheSettings|null $cacheSettings Кэш; пустой path по умолчанию.
     * @param bool $debug Исключения I/O кэша.
     *
     * @return SmartTableGateway Шлюз.
     */
    public static function make(
        IlluminateDatabaseConnection $databaseConnection,
        ?CacheSettings $cacheSettings = null,
        bool $debug = true,
    ): SmartTableGateway {
        return (new SmartTableSupport(
            $databaseConnection,
            $cacheSettings ?? CacheSettings::fromConfig(null),
            $debug,
        ))->makeGateway();
    }

    /**
     * Собирает каталог на переданном соединении.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param CacheSettings|null $cacheSettings Кэш; пустой path по умолчанию.
     * @param bool $debug Исключения I/O кэша.
     *
     * @return SmartTableCatalog Каталог.
     */
    public static function makeCatalog(
        IlluminateDatabaseConnection $databaseConnection,
        ?CacheSettings $cacheSettings = null,
        bool $debug = true,
    ): SmartTableCatalog {
        return (new SmartTableSupport(
            $databaseConnection,
            $cacheSettings ?? CacheSettings::fromConfig(null),
            $debug,
        ))->makeCatalog();
    }
}
