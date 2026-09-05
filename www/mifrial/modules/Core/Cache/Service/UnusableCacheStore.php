<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Service;

use Mifrial\Core\Cache\Exception\CacheInvalidException;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;

/**
 * Store при дырявом local.php: без connect и mkdir.
 */
final class UnusableCacheStore implements ICacheStore
{
    /**
     * Читает payload; непригодный store всегда отказ.
     *
     * @param string $cacheKey Ключ слота.
     *
     * @return never Управление не возвращается.
     *
     * @throws CacheInvalidException Всегда.
     */
    public function read(string $cacheKey): ?string
    {
        throw new CacheInvalidException('Cache store is not usable');
    }

    /**
     * Пишет payload; непригодный store всегда отказ.
     *
     * @param string $cacheKey Ключ слота.
     * @param string $payload Байты.
     * @param int $ttlSeconds TTL.
     * @param array<int, string> $tagNames Теги.
     *
     * @return void
     *
     * @throws CacheInvalidException Всегда.
     */
    public function write(string $cacheKey, string $payload, int $ttlSeconds, array $tagNames = []): void
    {
        throw new CacheInvalidException('Cache store is not usable');
    }

    /**
     * Удаляет ключи; пустой список — no-op.
     *
     * @param array<int, string> $cacheKeys Ключи.
     *
     * @return void
     *
     * @throws CacheInvalidException Если список не пуст.
     */
    public function deleteKeys(array $cacheKeys): void
    {
        if ($cacheKeys === []) {
            return;
        }

        throw new CacheInvalidException('Cache store is not usable');
    }

    /**
     * Сбрасывает теги; пустой список — no-op.
     *
     * @param array<int, string> $tagNames Теги.
     *
     * @return void
     *
     * @throws CacheInvalidException Если список не пуст.
     */
    public function flushTags(array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        throw new CacheInvalidException('Cache store is not usable');
    }

    /**
     * Всегда false.
     *
     * @return bool False.
     */
    public function isUsable(): bool
    {
        return false;
    }
}
