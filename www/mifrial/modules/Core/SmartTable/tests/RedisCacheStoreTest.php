<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;
use Mifrial\Core\SmartTable\Service\RedisCacheStore;
use PHPUnit\Framework\TestCase;
use Redis;

final class RedisCacheStoreTest extends TestCase
{
    /**
     * Пишет и сбрасывает тег на живом Redis или skip.
     *
     * @return void
     */
    public function testWriteAndFlushTagOrSkip(): void
    {
        if (!class_exists(Redis::class)) {
            self::markTestSkipped('ext-redis is not loaded');
        }

        $cacheKey = 'mifrial:redis:probe:' . uniqid('', true);
        $store = new RedisCacheStore('127.0.0.1', 6379);
        try {
            $store->write($cacheKey, "2000000000\n" . serialize(['ok' => true]), ['st:probe']);
        } catch (CacheDriverFailedException $exception) {
            self::markTestSkipped('redis socket is not available: ' . $exception->getMessage());
        }

        self::assertNotNull($store->read($cacheKey));
        $store->flushTags(['st:probe']);
        self::assertNull($store->read($cacheKey));
    }
}
