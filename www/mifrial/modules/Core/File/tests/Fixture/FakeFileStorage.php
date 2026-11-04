<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests\Fixture;

use Mifrial\Core\File\Exception\FileStorageException;
use Mifrial\Core\File\Interface\Service\IFileStorage;

/**
 * Хранилище в памяти для unit-тестов File.
 */
final class FakeFileStorage implements IFileStorage
{
    /**
     * @var array<string, string>
     */
    private array $files = [];

    /**
     * @var array<string, string>
     */
    private array $mimeByPath = [];

    /**
     * @var array<string, array{0: int, 1: int}|null>
     */
    private array $imageSizeByPath = [];

    /**
     * Регистрирует источник и его тип.
     *
     * @param string $sourcePath Абсолютный путь.
     * @param string $content Байты.
     * @param string $mime Тип.
     * @param array{0: int, 1: int}|null $imageSize Размеры.
     *
     * @return void
     */
    public function addSource(string $sourcePath, string $content, string $mime, ?array $imageSize = null): void
    {
        $this->files[$sourcePath] = $content;
        $this->mimeByPath[$sourcePath] = $mime;
        $this->imageSizeByPath[$sourcePath] = $imageSize;
    }

    /**
     * Содержимое шарда.
     *
     * @param string $storagePath Путь.
     *
     * @return string|null Байты или null.
     */
    public function contentAt(string $storagePath): ?string
    {
        return $this->files[$storagePath] ?? null;
    }

    /**
     * SHA-256 источника.
     *
     * @param string $sourcePath Путь.
     *
     * @return string Hex.
     */
    public function hash(string $sourcePath): string
    {
        return hash('sha256', $this->requireContent($sourcePath));
    }

    /**
     * Mime источника.
     *
     * @param string $sourcePath Путь.
     *
     * @return string Тип.
     */
    public function mimeType(string $sourcePath): string
    {
        if (!isset($this->mimeByPath[$sourcePath])) {
            throw new FileStorageException('Unknown fake source');
        }

        return $this->mimeByPath[$sourcePath];
    }

    /**
     * Размеры картинки.
     *
     * @param string $sourcePath Путь.
     *
     * @return array{0: int, 1: int}|null Размеры.
     */
    public function imageSize(string $sourcePath): ?array
    {
        return $this->imageSizeByPath[$sourcePath] ?? null;
    }

    /**
     * Шардовый путь.
     *
     * @param string $publicId Внешний id.
     * @param string $sha256 Хеш.
     *
     * @return string Путь.
     */
    public function storagePath(string $publicId, string $sha256): string
    {
        return substr($sha256, 0, 2) . '/' . substr($sha256, -2) . '/' . $publicId;
    }

    /**
     * Запоминает байты шарда.
     *
     * @param string $sourcePath Источник.
     * @param string $storagePath Путь.
     *
     * @return void
     */
    public function store(string $sourcePath, string $storagePath): void
    {
        $this->files[$storagePath] = $this->requireContent($sourcePath);
    }

    /**
     * Забывает шард.
     *
     * @param string $storagePath Путь.
     *
     * @return void
     */
    public function delete(string $storagePath): void
    {
        unset($this->files[$storagePath]);
    }

    /**
     * Порции байт шарда.
     *
     * @param string $storagePath Путь.
     * @param int $chunkSize Размер порции.
     *
     * @return iterable<string> Порции.
     */
    public function chunks(string $storagePath, int $chunkSize): iterable
    {
        $content = $this->requireContent($storagePath);
        foreach (str_split($content, max(1, $chunkSize)) as $chunk) {
            yield $chunk;
        }
    }

    /**
     * Копирует шард во внешний файл.
     *
     * @param string $storagePath Путь.
     * @param string $targetPath Назначение.
     *
     * @return void
     */
    public function exportTo(string $storagePath, string $targetPath): void
    {
        $this->files[$targetPath] = $this->requireContent($storagePath);
    }

    /**
     * Байты по любому известному пути: шарду или экспортированной копии.
     *
     * @param string $path Путь.
     *
     * @return string|null Байты или null.
     */
    public function contentAtAnyPath(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    /**
     * Байты источника или ошибка.
     *
     * @param string $sourcePath Путь.
     *
     * @return string Байты.
     */
    private function requireContent(string $sourcePath): string
    {
        if (!isset($this->files[$sourcePath])) {
            throw new FileStorageException('Unknown fake source');
        }

        return $this->files[$sourcePath];
    }
}
