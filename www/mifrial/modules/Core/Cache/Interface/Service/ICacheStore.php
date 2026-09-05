<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Interface\Service;

use Mifrial\Core\Cache\Exception\CacheDriverFailedException;
use Mifrial\Core\Cache\Exception\CacheInvalidException;

/**
 * Байты по ключу с TTL и опциональными тегами.
 */
interface ICacheStore
{
    public const MAX_TTL_SECONDS = 2592000;

    /**
     * Читает payload ключа без конверта TTL.
     *
     * @param string $cacheKey Ключ слота.
     *
     * @return string|null Байты потребителя или промах.
     *
     * @throws CacheInvalidException Если ключ пуст или store непригоден.
     * @throws CacheDriverFailedException Если I/O драйвера упал.
     */
    public function read(string $cacheKey): ?string;

    /**
     * Пишет payload с TTL, затем вешает ключ на теги.
     *
     * @param string $cacheKey Ключ слота.
     * @param string $payload Байты потребителя.
     * @param int $ttlSeconds Срок 1..2592000.
     * @param array<int, string> $tagNames Теги; пусто — без множеств.
     *
     * @return void
     *
     * @throws CacheInvalidException Если аргументы или store непригодны.
     * @throws CacheDriverFailedException Если I/O драйвера упал.
     */
    public function write(string $cacheKey, string $payload, int $ttlSeconds, array $tagNames = []): void;

    /**
     * Удаляет payload ключей.
     *
     * @param array<int, string> $cacheKeys Ключи; пусто — no-op.
     *
     * @return void
     *
     * @throws CacheInvalidException Если ключ пуст или store непригоден.
     * @throws CacheDriverFailedException Если I/O драйвера упал.
     */
    public function deleteKeys(array $cacheKeys): void;

    /**
     * Сбрасывает ключи всех переданных тегов.
     *
     * @param array<int, string> $tagNames Теги; пусто — no-op.
     *
     * @return void
     *
     * @throws CacheInvalidException Если тег пуст или store непригоден.
     * @throws CacheDriverFailedException Если I/O драйвера упал.
     */
    public function flushTags(array $tagNames): void;

    /**
     * Можно ли писать и сбрасывать (конфиг не дырявый).
     *
     * @return bool False при пустом path / дырявом redis / неизвестном драйвере.
     */
    public function isUsable(): bool;
}
