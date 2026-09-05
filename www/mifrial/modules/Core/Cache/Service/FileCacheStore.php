<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Service;

use Closure;
use Mifrial\Core\Cache\Exception\CacheDriverFailedException;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Throwable;

/**
 * File-store: payload-файлы, индекс тегов, конверт TTL.
 */
final class FileCacheStore implements ICacheStore
{
    private readonly FileCacheTagIndex $tagIndex;

    private readonly CacheEnvelope $cacheEnvelope;

    /**
     * Создаёт store в каталоге.
     *
     * @param string $basePath Каталог кэша.
     * @param Closure|null $clock Unix-секунды для тестов expire.
     *
     * @return void
     */
    public function __construct(
        private readonly string $basePath,
        private readonly ?Closure $clock = null,
    ) {
        $this->tagIndex = new FileCacheTagIndex($basePath);
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
     * @throws CacheDriverFailedException Если I/O файла упал.
     */
    public function read(string $cacheKey): ?string
    {
        CacheStoreGuard::assertKey($cacheKey);
        $filePath = $this->payloadPath($cacheKey);
        if (!is_file($filePath)) {
            return null;
        }

        return $this->unpackOrDelete($cacheKey, $this->lockedRead($filePath));
    }

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
     * @throws CacheInvalidException Если аргументы непригодны.
     * @throws CacheDriverFailedException Если I/O файла упал.
     */
    public function write(string $cacheKey, string $payload, int $ttlSeconds, array $tagNames = []): void
    {
        CacheStoreGuard::assertKey($cacheKey);
        CacheStoreGuard::assertTtl($ttlSeconds);
        CacheStoreGuard::assertTags($tagNames);
        $this->ensureDirectories();
        $this->lockedWrite(
            $this->payloadPath($cacheKey),
            $this->cacheEnvelope->pack($payload, $this->now() + $ttlSeconds),
        );
        foreach ($tagNames as $tagName) {
            $this->tagIndex->add($tagName, $cacheKey);
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
     * @throws CacheDriverFailedException Если unlink не удался.
     */
    public function deleteKeys(array $cacheKeys): void
    {
        if ($cacheKeys === []) {
            return;
        }

        CacheStoreGuard::assertKeys($cacheKeys);
        foreach ($cacheKeys as $cacheKey) {
            $filePath = $this->payloadPath($cacheKey);
            if (is_file($filePath) && !unlink($filePath) && is_file($filePath)) {
                throw new CacheDriverFailedException();
            }
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
     * @throws CacheDriverFailedException Если индекс недоступен.
     */
    public function flushTags(array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        CacheStoreGuard::assertTags($tagNames);
        $cacheKeys = [];
        foreach ($tagNames as $tagName) {
            foreach ($this->tagIndex->keys($tagName) as $cacheKey) {
                $cacheKeys[$cacheKey] = true;
            }

            $this->tagIndex->clear($tagName);
        }

        $this->deleteKeys(array_keys($cacheKeys));
    }

    /**
     * File-store из factory всегда пригоден до I/O.
     *
     * @return bool True.
     */
    public function isUsable(): bool
    {
        return true;
    }

    /**
     * Снимает конверт или удаляет истёкший слот.
     *
     * @param string $cacheKey Ключ.
     * @param string $raw Байты файла.
     *
     * @return string|null Payload или промах.
     *
     * @throws CacheDriverFailedException Если unlink истёкшего слота не удался.
     */
    private function unpackOrDelete(string $cacheKey, string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        $payload = $this->cacheEnvelope->unpack($raw, $this->now());
        if ($payload !== null) {
            return $payload;
        }

        $this->deleteKeys([$cacheKey]);

        return null;
    }

    /**
     * Создаёт каталоги payload и тегов.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если mkdir не удался.
     */
    private function ensureDirectories(): void
    {
        foreach ([$this->basePath, $this->basePath . '/k', $this->basePath . '/t'] as $directory) {
            $this->ensureDirectory($directory);
        }
    }

    /**
     * Создаёт один каталог.
     *
     * @param string $directory Путь.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если путь занят файлом или mkdir не удался.
     */
    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (file_exists($directory) || !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new CacheDriverFailedException();
        }
    }

    /**
     * Путь payload-файла.
     *
     * @param string $cacheKey Ключ.
     *
     * @return string Путь.
     */
    private function payloadPath(string $cacheKey): string
    {
        return $this->basePath . '/k/' . hash('sha256', $cacheKey);
    }

    /**
     * Читает payload с shared lock.
     *
     * @param string $filePath Путь.
     *
     * @return string Байты.
     *
     * @throws CacheDriverFailedException Если чтение не удалось.
     */
    private function lockedRead(string $filePath): string
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new CacheDriverFailedException();
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new CacheDriverFailedException();
            }

            $payload = stream_get_contents($handle);

            return is_string($payload) ? $payload : '';
        } finally {
            fclose($handle);
        }
    }

    /**
     * Пишет payload с exclusive lock.
     *
     * @param string $filePath Путь.
     * @param string $payload Байты.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если запись не удалась.
     */
    private function lockedWrite(string $filePath, string $payload): void
    {
        $handle = fopen($filePath, 'c+');
        if ($handle === false) {
            throw new CacheDriverFailedException();
        }

        try {
            if (!flock($handle, LOCK_EX) || !ftruncate($handle, 0) || fwrite($handle, $payload) === false) {
                throw new CacheDriverFailedException();
            }
        } catch (Throwable $throwable) {
            fclose($handle);

            throw $throwable instanceof CacheDriverFailedException
                ? $throwable
                : new CacheDriverFailedException($throwable);
        }

        fclose($handle);
    }

    /**
     * Текущие unix-секунды.
     *
     * @return int Секунды.
     */
    private function now(): int
    {
        return $this->clock instanceof Closure ? ($this->clock)() : time();
    }
}
