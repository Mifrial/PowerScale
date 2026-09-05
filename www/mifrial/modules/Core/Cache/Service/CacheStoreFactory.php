<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Service;

use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Kernel\Dto\CacheSettings;
use Redis;

/**
 * Собирает file/redis или непригодный store по CacheSettings.
 */
final class CacheStoreFactory
{
    /**
     * Открывает store без throw на дырявом конфиге.
     *
     * @param CacheSettings $cacheSettings Срез local.php.
     *
     * @return ICacheStore Store; при дыре — isUsable false.
     */
    public function open(CacheSettings $cacheSettings): ICacheStore
    {
        $driver = $cacheSettings->driver();
        if ($driver === 'file' && $cacheSettings->path() !== '') {
            return new FileCacheStore($cacheSettings->path());
        }

        if ($driver === 'redis' && $this->canOpenRedis($cacheSettings)) {
            return new RedisCacheStore($cacheSettings->redisHost(), $cacheSettings->redisPort());
        }

        return new UnusableCacheStore();
    }

    /**
     * Хост, порт и расширение на месте.
     *
     * @param CacheSettings $cacheSettings Срез.
     *
     * @return bool Пригодно для RedisCacheStore.
     */
    private function canOpenRedis(CacheSettings $cacheSettings): bool
    {
        return $cacheSettings->redisHost() !== ''
            && $cacheSettings->redisPort() > 0
            && class_exists(Redis::class);
    }
}
