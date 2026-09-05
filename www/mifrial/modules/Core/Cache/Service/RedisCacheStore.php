<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Service;

use Mifrial\Core\Cache\Exception\CacheDriverFailedException;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Redis;
use Throwable;

/**
 * Redis-store: SET EX, множества тегов, конверт TTL.
 */
final class RedisCacheStore implements ICacheStore
{
    private ?Redis $redisClient = null;

    private readonly CacheEnvelope $cacheEnvelope;

    /**
     * Создаёт store к сокету Redis.
     *
     * @param string $redisHost Хост.
     * @param int $redisPort Порт.
     *
     * @return void
     */
    public function __construct(
        private readonly string $redisHost,
        private readonly int $redisPort,
    ) {
        $this->cacheEnvelope = new CacheEnvelope();
    }

    /**
     * Читает payload ключа без конверта TTL.
     *
     * @param string $cacheKey Ключ слота.
     *
     * @return string|null Байты потребителя или промах.
     *
     * @throws CacheInvalidException Если ключ пуст.
     * @throws CacheDriverFailedException Если GET не удался.
     */
    public function read(string $cacheKey): ?string
    {
        CacheStoreGuard::assertKey($cacheKey);
        try {
            $raw = $this->client()->get($cacheKey);
        } catch (Throwable $throwable) {
            throw $this->asDriverFailed($throwable);
        }

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $payload = $this->cacheEnvelope->unpack($raw, time());
        if ($payload === null) {
            $this->deleteKeys([$cacheKey]);

            return null;
        }

        return $payload;
    }

    /**
     * Пишет payload с SET EX, затем SADD в теги.
     *
     * @param string $cacheKey Ключ слота.
     * @param string $payload Байты потребителя.
     * @param int $ttlSeconds Срок 1..2592000.
     * @param array<int, string> $tagNames Теги; пусто — без множеств.
     *
     * @return void
     *
     * @throws CacheInvalidException Если аргументы непригодны.
     * @throws CacheDriverFailedException Если SET/SADD не удался.
     */
    public function write(string $cacheKey, string $payload, int $ttlSeconds, array $tagNames = []): void
    {
        CacheStoreGuard::assertKey($cacheKey);
        CacheStoreGuard::assertTtl($ttlSeconds);
        CacheStoreGuard::assertTags($tagNames);
        $packed = $this->cacheEnvelope->pack($payload, time() + $ttlSeconds);
        try {
            $this->client()->setex($cacheKey, $ttlSeconds, $packed);
            foreach ($tagNames as $tagName) {
                $this->client()->sAdd('stg:' . $tagName, $cacheKey);
            }
        } catch (Throwable $throwable) {
            throw $this->asDriverFailed($throwable);
        }
    }

    /**
     * Удаляет payload ключей.
     *
     * @param array<int, string> $cacheKeys Ключи; пусто — no-op.
     *
     * @return void
     *
     * @throws CacheInvalidException Если ключ пуст.
     * @throws CacheDriverFailedException Если DEL не удался.
     */
    public function deleteKeys(array $cacheKeys): void
    {
        if ($cacheKeys === []) {
            return;
        }

        CacheStoreGuard::assertKeys($cacheKeys);
        try {
            $this->client()->del($cacheKeys);
        } catch (Throwable $throwable) {
            throw $this->asDriverFailed($throwable);
        }
    }

    /**
     * Сбрасывает ключи всех переданных тегов.
     *
     * @param array<int, string> $tagNames Теги; пусто — no-op.
     *
     * @return void
     *
     * @throws CacheInvalidException Если тег пуст.
     * @throws CacheDriverFailedException Если множества недоступны.
     */
    public function flushTags(array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        CacheStoreGuard::assertTags($tagNames);
        $cacheKeys = [];
        try {
            foreach ($tagNames as $tagName) {
                foreach ($this->tagMembers($tagName) as $cacheKey) {
                    $cacheKeys[$cacheKey] = true;
                }

                $this->client()->del('stg:' . $tagName);
            }
        } catch (Throwable $throwable) {
            throw $this->asDriverFailed($throwable);
        }

        $this->deleteKeys(array_keys($cacheKeys));
    }

    /**
     * Redis-store из factory всегда пригоден до connect.
     *
     * @return bool True.
     */
    public function isUsable(): bool
    {
        return true;
    }

    /**
     * Ключи множества тега.
     *
     * @param string $tagName Тег.
     *
     * @return array<int, string> Ключи.
     *
     * @throws CacheDriverFailedException Если SMEMBERS не удался.
     */
    private function tagMembers(string $tagName): array
    {
        $members = $this->client()->sMembers('stg:' . $tagName);
        $cacheKeys = [];
        if (!is_array($members)) {
            return $cacheKeys;
        }

        foreach ($members as $member) {
            if (is_string($member)) {
                $cacheKeys[] = $member;
            }
        }

        return $cacheKeys;
    }

    /**
     * Открывает клиент Redis.
     *
     * @return Redis Живой клиент.
     *
     * @throws CacheDriverFailedException Если connect не удался.
     */
    private function client(): Redis
    {
        if ($this->redisClient instanceof Redis) {
            return $this->redisClient;
        }

        try {
            $redisClient = new Redis();
            $connected = $redisClient->connect($this->redisHost, $this->redisPort, 1.0);
        } catch (Throwable $throwable) {
            throw $this->asDriverFailed($throwable);
        }

        if ($connected !== true) {
            throw new CacheDriverFailedException();
        }

        $this->redisClient = $redisClient;

        return $redisClient;
    }

    /**
     * Не оборачивает уже типизированный отказ драйвера.
     *
     * @param Throwable $throwable Причина.
     *
     * @return CacheDriverFailedException Отказ I/O.
     */
    private function asDriverFailed(Throwable $throwable): CacheDriverFailedException
    {
        return $throwable instanceof CacheDriverFailedException
            ? $throwable
            : new CacheDriverFailedException($throwable);
    }
}
