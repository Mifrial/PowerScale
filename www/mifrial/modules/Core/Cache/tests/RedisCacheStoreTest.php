<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Tests;

use Mifrial\Core\Cache\Exception\CacheDriverFailedException;
use Mifrial\Core\Cache\Service\RedisCacheStore;
use PHPUnit\Framework\TestCase;
use Redis;

final class RedisCacheStoreTest extends TestCase
{
    /**
     * Пишет с TTL и сбрасывает тег на живом Redis или skip.
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
            $store->write($cacheKey, serialize(['ok' => true]), 60, ['st:probe']);
        } catch (CacheDriverFailedException $exception) {
            self::markTestSkipped('redis socket is not available: ' . $exception->getMessage());
        }

        self::assertNotNull($store->read($cacheKey));
        $store->flushTags(['st:probe']);
        self::assertNull($store->read($cacheKey));
    }
}
