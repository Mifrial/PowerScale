<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;
use Throwable;

/**
 * File-store: payload-файлы и индекс тегов getList.
 */
final class FileCacheStore
{
    private readonly FileCacheTagIndex $tagIndex;

    /**
     * Создаёт store в каталоге.
     *
     * @param string $basePath Каталог кэша.
     *
     * @return void
     */
    public function __construct(
        private readonly string $basePath,
    ) {
        $this->tagIndex = new FileCacheTagIndex($basePath);
    }

    /**
     * Читает сырой payload ключа или null.
     *
     * @param string $cacheKey Ключ слота.
     *
     * @return string|null Байты или miss.
     *
     * @throws CacheDriverFailedException Если чтение файла не удалось.
     */
    public function read(string $cacheKey): ?string
    {
        $filePath = $this->payloadPath($cacheKey);
        if (!is_file($filePath)) {
            return null;
        }

        $payload = $this->lockedRead($filePath);

        return $payload === '' ? null : $payload;
    }

    /**
     * Пишет payload и добавляет ключ в индексы тегов.
     *
     * @param string $cacheKey Ключ слота.
     * @param string $payload Байты с expire.
     * @param array<int, string> $tagNames Теги getList; пусто для get.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если запись не удалась.
     */
    public function write(string $cacheKey, string $payload, array $tagNames): void
    {
        $this->ensureDirectories();
        $this->lockedWrite($this->payloadPath($cacheKey), $payload);
        foreach ($tagNames as $tagName) {
            $this->tagIndex->add($tagName, $cacheKey);
        }
    }

    /**
     * Удаляет payload ключей.
     *
     * @param array<int, string> $cacheKeys Ключи.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если unlink не удался.
     */
    public function deleteKeys(array $cacheKeys): void
    {
        foreach ($cacheKeys as $cacheKey) {
            $filePath = $this->payloadPath($cacheKey);
            if (is_file($filePath) && !unlink($filePath) && is_file($filePath)) {
                throw new CacheDriverFailedException();
            }
        }
    }

    /**
     * Сбрасывает ключи всех переданных тегов (OR).
     *
     * @param array<int, string> $tagNames Теги.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если индекс недоступен.
     */
    public function flushTags(array $tagNames): void
    {
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
}
