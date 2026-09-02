<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Cache;

use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;
use Redis;
use Throwable;

/**
 * Redis-store: payload SET и множества ключей getList по тегу.
 */
final class RedisCacheStore
{
    private ?Redis $redisClient = null;

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
    }

    /**
     * Читает сырой payload ключа или null.
     *
     * @param string $cacheKey Ключ слота.
     *
     * @return string|null Байты или miss.
     *
     * @throws CacheDriverFailedException Если GET не удался.
     */
    public function read(string $cacheKey): ?string
    {
        try {
            $payload = $this->client()->get($cacheKey);
        } catch (Throwable $throwable) {
            throw new CacheDriverFailedException($throwable);
        }

        return is_string($payload) ? $payload : null;
    }

    /**
     * Пишет payload и добавляет ключ в множества тегов.
     *
     * @param string $cacheKey Ключ слота.
     * @param string $payload Байты с expire.
     * @param array<int, string> $tagNames Теги getList; пусто для get.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если SET/SADD не удался.
     */
    public function write(string $cacheKey, string $payload, array $tagNames): void
    {
        try {
            $this->client()->set($cacheKey, $payload);
            foreach ($tagNames as $tagName) {
                $this->client()->sAdd('stg:' . $tagName, $cacheKey);
            }
        } catch (Throwable $throwable) {
            throw new CacheDriverFailedException($throwable);
        }
    }

    /**
     * Удаляет payload ключей.
     *
     * @param array<int, string> $cacheKeys Ключи.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если DEL не удался.
     */
    public function deleteKeys(array $cacheKeys): void
    {
        if ($cacheKeys === []) {
            return;
        }

        try {
            $this->client()->del($cacheKeys);
        } catch (Throwable $throwable) {
            throw new CacheDriverFailedException($throwable);
        }
    }

    /**
     * Сбрасывает ключи всех переданных тегов (OR).
     *
     * @param array<int, string> $tagNames Теги.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если множества недоступны.
     */
    public function flushTags(array $tagNames): void
    {
        $cacheKeys = [];
        try {
            foreach ($tagNames as $tagName) {
                foreach ($this->tagMembers($tagName) as $cacheKey) {
                    $cacheKeys[$cacheKey] = true;
                }

                $this->client()->del('stg:' . $tagName);
            }
        } catch (Throwable $throwable) {
            throw new CacheDriverFailedException($throwable);
        }

        $this->deleteKeys(array_keys($cacheKeys));
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
            throw new CacheDriverFailedException($throwable);
        }

        if ($connected !== true) {
            throw new CacheDriverFailedException();
        }

        $this->redisClient = $redisClient;

        return $redisClient;
    }
}
