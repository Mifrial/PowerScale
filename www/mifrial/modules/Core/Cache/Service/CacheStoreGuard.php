<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Service;

use Mifrial\Core\Cache\Exception\CacheInvalidException;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;

/**
 * Проверки ключа, TTL и тегов до I/O.
 */
final class CacheStoreGuard
{
    /**
     * Отвергает пустой ключ.
     *
     * @param string $cacheKey Ключ.
     *
     * @return void
     *
     * @throws CacheInvalidException Если ключ пуст.
     */
    public static function assertKey(string $cacheKey): void
    {
        if ($cacheKey === '') {
            throw new CacheInvalidException('Cache key is empty');
        }
    }

    /**
     * Отвергает TTL вне 1..2592000.
     *
     * @param int $ttlSeconds Секунды.
     *
     * @return void
     *
     * @throws CacheInvalidException Если TTL вне диапазона.
     */
    public static function assertTtl(int $ttlSeconds): void
    {
        if ($ttlSeconds < 1 || $ttlSeconds > ICacheStore::MAX_TTL_SECONDS) {
            throw new CacheInvalidException('Cache TTL is out of range');
        }
    }

    /**
     * Отвергает пустые имена тегов.
     *
     * @param array<int, string> $tagNames Теги.
     *
     * @return void
     *
     * @throws CacheInvalidException Если тег пуст.
     */
    public static function assertTags(array $tagNames): void
    {
        foreach ($tagNames as $tagName) {
            if (!is_string($tagName) || $tagName === '') {
                throw new CacheInvalidException('Cache tag is empty');
            }
        }
    }

    /**
     * Проверяет ключи списка.
     *
     * @param array<int, string> $cacheKeys Ключи.
     *
     * @return void
     *
     * @throws CacheInvalidException Если ключ пуст.
     */
    public static function assertKeys(array $cacheKeys): void
    {
        foreach ($cacheKeys as $cacheKey) {
            if (!is_string($cacheKey) || $cacheKey === '') {
                throw new CacheInvalidException('Cache key is empty');
            }
        }
    }
}
