<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\SmartTable\Exception\Cache\CacheConfigInvalidException;
use Redis;

/**
 * Ленивое открытие file/redis store по CacheSettings.
 */
final class CacheStoreFactory
{
    /**
     * Создаёт фабрику store.
     *
     * @param CacheSettings $cacheSettings Срез local.php.
     *
     * @return void
     */
    public function __construct(
        private readonly CacheSettings $cacheSettings,
    ) {
    }

    /**
     * Можно ли открыть store для invalidate.
     *
     * @return bool False при пустом path или дырявом redis.
     */
    public function canOpen(): bool
    {
        $driver = $this->cacheSettings->driver();
        if ($driver === 'file') {
            return $this->cacheSettings->path() !== '';
        }

        return $driver === 'redis'
            && $this->cacheSettings->redisHost() !== ''
            && $this->cacheSettings->redisPort() > 0;
    }

    /**
     * Собирает store.
     *
     * @return FileCacheStore|RedisCacheStore Store.
     *
     * @throws CacheConfigInvalidException Если конфиг непригоден.
     */
    public function open(): FileCacheStore|RedisCacheStore
    {
        $driver = $this->cacheSettings->driver();
        if ($driver === 'file') {
            return $this->openFileStore();
        }

        if ($driver !== 'redis') {
            throw new CacheConfigInvalidException('Cache driver is unknown');
        }

        return $this->openRedisStore();
    }

    /**
     * Открывает file-store.
     *
     * @return FileCacheStore Store.
     *
     * @throws CacheConfigInvalidException Если path пуст.
     */
    private function openFileStore(): FileCacheStore
    {
        if ($this->cacheSettings->path() === '') {
            throw new CacheConfigInvalidException('Cache file path is empty');
        }

        return new FileCacheStore($this->cacheSettings->path());
    }

    /**
     * Открывает redis-store.
     *
     * @return RedisCacheStore Store.
     *
     * @throws CacheConfigInvalidException Если host/port/ext нет.
     */
    private function openRedisStore(): RedisCacheStore
    {
        if ($this->cacheSettings->redisHost() === '' || $this->cacheSettings->redisPort() < 1) {
            throw new CacheConfigInvalidException('Cache redis host or port is missing');
        }

        if (!class_exists(Redis::class)) {
            throw new CacheConfigInvalidException('Cache redis extension is missing');
        }

        return new RedisCacheStore($this->cacheSettings->redisHost(), $this->cacheSettings->redisPort());
    }
}
