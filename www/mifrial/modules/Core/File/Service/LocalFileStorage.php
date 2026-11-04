<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Exception\FileStorageException;
use Mifrial\Core\File\Interface\Service\IFileStorage;

/**
 * Байты файлов на локальном диске в шардах `ab/cd/public_id`.
 */
final class LocalFileStorage implements IFileStorage
{
    private const DIRECTORY_MODE = 0770;

    private const FILE_MODE = 0640;

    private readonly string $rootPath;

    /**
     * Запоминает корень; каталоги создаются только при записи.
     *
     * @param string $rootPath Корень файлов.
     *
     * @return void
     */
    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/');
    }

    /**
     * Считает SHA-256 файла-источника.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return string Hex.
     */
    public function hash(string $sourcePath): string
    {
        $hash = hash_file('sha256', $sourcePath);
        if (!is_string($hash)) {
            throw new FileStorageException('Cannot hash a file');
        }

        return $hash;
    }

    /**
     * Определяет серверный mime.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return string type/subtype.
     */
    public function mimeType(string $sourcePath): string
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            throw new FileStorageException('fileinfo is unavailable');
        }

        try {
            $mime = finfo_file($fileInfo, $sourcePath);
        } finally {
            finfo_close($fileInfo);
        }

        if (!is_string($mime) || $mime === '') {
            throw new FileStorageException('Cannot detect a file type');
        }

        return $mime;
    }

    /**
     * Размеры картинки.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return array{0: int, 1: int}|null Ширина и высота или null.
     */
    public function imageSize(string $sourcePath): ?array
    {
        $imageSize = $this->withoutWarnings(fn (): mixed => getimagesize($sourcePath));
        if (!is_array($imageSize)) {
            return null;
        }

        $width = $imageSize[0] ?? null;
        $height = $imageSize[1] ?? null;
        if (!is_int($width) || !is_int($height)) {
            return null;
        }

        return [$width, $height];
    }

    /**
     * Относительный путь шарда.
     *
     * @param string $publicId Внешний id.
     * @param string $sha256 Hex.
     *
     * @return string Путь.
     */
    public function storagePath(string $publicId, string $sha256): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $publicId) !== 1 || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            throw new FileStorageException('File storage key is invalid');
        }

        return substr($sha256, 0, 2) . '/' . substr($sha256, -2) . '/' . $publicId;
    }

    /**
     * Переносит принятый файл в шард.
     *
     * @param string $sourcePath Абсолютный tmp.
     * @param string $storagePath Относительный путь.
     *
     * @return void
     */
    public function store(string $sourcePath, string $storagePath): void
    {
        $targetPath = $this->absolutePath($storagePath);
        $this->ensureDirectory($this->rootPath);
        $this->ensureDirectory(dirname($targetPath));

        if (is_file($targetPath)) {
            throw new FileStorageException('File storage target already exists');
        }

        $moved = $this->withoutWarnings(fn (): bool => rename($sourcePath, $targetPath));
        if (!$moved) {
            $copied = $this->withoutWarnings(fn (): bool => copy($sourcePath, $targetPath));
            if (!$copied) {
                $this->withoutWarnings(fn (): bool => is_file($targetPath) && unlink($targetPath));

                throw new FileStorageException('Cannot move an uploaded file into storage');
            }

            $this->withoutWarnings(fn (): bool => unlink($sourcePath));
        }

        $this->withoutWarnings(fn (): bool => chmod($targetPath, self::FILE_MODE));
    }

    /**
     * Удаляет файл шарда, если он есть.
     *
     * @param string $storagePath Относительный путь.
     *
     * @return void
     */
    public function delete(string $storagePath): void
    {
        $targetPath = $this->absolutePath($storagePath);
        $deleted = $this->withoutWarnings(fn (): bool => unlink($targetPath));
        if (is_file($targetPath) && !$deleted) {
            throw new FileStorageException('Cannot delete a stored file');
        }
    }

    /**
     * Отдаёт байты файла порциями.
     *
     * @param string $storagePath Относительный путь.
     * @param int $chunkSize Размер порции.
     *
     * @return iterable<string> Порции байт.
     */
    public function chunks(string $storagePath, int $chunkSize): iterable
    {
        $targetPath = $this->absolutePath($storagePath);
        $handle = $this->withoutWarnings(fn (): mixed => fopen($targetPath, 'rb'));
        if ($handle === false) {
            throw new FileStorageException('Cannot open a stored file');
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new FileStorageException('Cannot read a stored file');
                }

                if ($chunk === '') {
                    break;
                }

                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Абсолютный путь без выхода из корня.
     *
     * @param string $storagePath Относительный путь.
     *
     * @return string Абсолютный путь.
     */
    private function absolutePath(string $storagePath): string
    {
        if (
            $storagePath === ''
            || str_starts_with($storagePath, '/')
            || str_contains($storagePath, '..')
        ) {
            throw new FileStorageException('File storage path is invalid');
        }

        return $this->rootPath . '/' . $storagePath;
    }

    /**
     * Выполняет ФС-операцию без предупреждений; результат проверяет вызывающий.
     *
     * @param callable $operation Операция.
     *
     * @return mixed Результат.
     */
    private function withoutWarnings(callable $operation): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Создаёт каталог, если его нет.
     *
     * @param string $directoryPath Абсолютный путь.
     *
     * @return void
     */
    private function ensureDirectory(string $directoryPath): void
    {
        if (is_dir($directoryPath)) {
            return;
        }

        $created = $this->withoutWarnings(fn (): bool => mkdir($directoryPath, self::DIRECTORY_MODE, true));
        if (!$created && !is_dir($directoryPath)) {
            throw new FileStorageException('Cannot create a file storage directory');
        }
    }
}
