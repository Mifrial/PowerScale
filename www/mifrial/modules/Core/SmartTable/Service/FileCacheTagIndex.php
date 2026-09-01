<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Exception\Cache\CacheDriverFailedException;

/**
 * Индекс тегов file-store: ключи getList в файле с flock.
 */
final class FileCacheTagIndex
{
    /**
     * Создаёт индекс в каталоге тегов.
     *
     * @param string $basePath Корень store.
     *
     * @return void
     */
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    /**
     * Добавляет ключ в индекс тега.
     *
     * @param string $tagName Тег.
     * @param string $cacheKey Ключ.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если индекс недоступен.
     */
    public function add(string $tagName, string $cacheKey): void
    {
        $handle = $this->openLocked($this->tagPath($tagName));
        try {
            $keys = $this->readLines($handle);
            $keys[$cacheKey] = true;
            $this->rewrite($handle, implode("\n", array_keys($keys)) . "\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * Читает ключи тега.
     *
     * @param string $tagName Тег.
     *
     * @return array<int, string> Ключи.
     *
     * @throws CacheDriverFailedException Если индекс недоступен.
     */
    public function keys(string $tagName): array
    {
        $filePath = $this->tagPath($tagName);
        if (!is_file($filePath)) {
            return [];
        }

        $handle = $this->openLocked($filePath);
        try {
            return array_keys($this->readLines($handle));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Очищает индекс тега.
     *
     * @param string $tagName Тег.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если индекс недоступен.
     */
    public function clear(string $tagName): void
    {
        $filePath = $this->tagPath($tagName);
        if (!is_file($filePath)) {
            return;
        }

        $handle = $this->openLocked($filePath);
        try {
            $this->rewrite($handle, '');
        } finally {
            fclose($handle);
        }
    }

    /**
     * Путь индексного файла.
     *
     * @param string $tagName Тег.
     *
     * @return string Путь.
     */
    private function tagPath(string $tagName): string
    {
        return $this->basePath . '/t/' . hash('sha256', $tagName);
    }

    /**
     * Открывает файл с flock.
     *
     * @param string $filePath Путь.
     *
     * @return resource Хендл.
     *
     * @throws CacheDriverFailedException Если open/flock не удался.
     */
    private function openLocked(string $filePath)
    {
        $handle = fopen($filePath, 'c+');
        if ($handle === false) {
            throw new CacheDriverFailedException();
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new CacheDriverFailedException();
        }

        return $handle;
    }

    /**
     * Читает ключи из залоченного хендла.
     *
     * @param resource $handle Файл.
     *
     * @return array<string, true> Ключи.
     */
    private function readLines($handle): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        $keys = [];
        if (!is_string($contents) || $contents === '') {
            return $keys;
        }

        foreach (explode("\n", $contents) as $line) {
            if ($line !== '') {
                $keys[$line] = true;
            }
        }

        return $keys;
    }

    /**
     * Перезаписывает файл.
     *
     * @param resource $handle Файл.
     * @param string $body Байты.
     *
     * @return void
     *
     * @throws CacheDriverFailedException Если запись не удалась.
     */
    private function rewrite($handle, string $body): void
    {
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $body) === false) {
            throw new CacheDriverFailedException();
        }
    }
}
