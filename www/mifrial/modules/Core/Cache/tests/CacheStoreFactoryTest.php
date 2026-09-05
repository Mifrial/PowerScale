<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Tests;

use Mifrial\Core\Cache\Service\CacheStoreFactory;
use Mifrial\Core\Cache\Service\FileCacheStore;
use Mifrial\Core\Cache\Service\UnusableCacheStore;
use Mifrial\Core\Kernel\Dto\CacheSettings;
use PHPUnit\Framework\TestCase;

final class CacheStoreFactoryTest extends TestCase
{
    /**
     * Пустой path, неизвестный драйвер, дырявый redis — непригодный объект.
     *
     * @return void
     */
    public function testLeakyConfigReturnsUnusable(): void
    {
        $factory = new CacheStoreFactory();
        self::assertInstanceOf(
            UnusableCacheStore::class,
            $factory->open(CacheSettings::fromConfig(null)),
        );
        self::assertFalse($factory->open(CacheSettings::fromConfig(null))->isUsable());
        self::assertFalse(
            $factory->open(CacheSettings::fromConfig(['driver' => 'memcached', 'path' => '/tmp']))->isUsable(),
        );
        self::assertFalse(
            $factory->open(CacheSettings::fromConfig([
                'driver' => 'redis',
                'redis' => ['host' => '', 'port' => 0],
            ]))->isUsable(),
        );
    }

    /**
     * File с path — usable FileCacheStore.
     *
     * @return void
     */
    public function testFilePathOpensFileStore(): void
    {
        $path = sys_get_temp_dir() . '/mifrial-cache-factory-' . uniqid('', true);
        $store = (new CacheStoreFactory())->open(CacheSettings::fromConfig([
            'driver' => 'file',
            'path' => $path,
        ]));
        self::assertInstanceOf(FileCacheStore::class, $store);
        self::assertTrue($store->isUsable());
    }
}
