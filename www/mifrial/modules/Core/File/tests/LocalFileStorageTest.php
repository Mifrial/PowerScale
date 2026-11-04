<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Exception\FileStorageException;
use Mifrial\Core\File\Service\LocalFileStorage;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LocalFileStorageTest extends TestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $rootPath = '';

    private string $sourcePath = '';

    /**
     * Готовит временные каталоги.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/opencode-file-' . bin2hex(random_bytes(6));
        $this->sourcePath = $this->rootPath . '-source.bin';
        file_put_contents($this->sourcePath, (string) base64_decode(self::PNG_BASE64, true));
    }

    /**
     * Сносит временные файлы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (is_file($this->sourcePath)) {
            unlink($this->sourcePath);
        }

        if (is_dir($this->rootPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            /** @var SplFileInfo $entry */
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }

            rmdir($this->rootPath);
        }
    }

    /**
     * Путь шарда, хеш, тип и размеры.
     *
     * @return void
     */
    public function testReadsSourceMetadata(): void
    {
        $storage = new LocalFileStorage($this->rootPath);
        $sha256 = $storage->hash($this->sourcePath);
        self::assertSame(64, strlen($sha256));
        self::assertSame('image/png', $storage->mimeType($this->sourcePath));
        self::assertSame([1, 1], $storage->imageSize($this->sourcePath));
        self::assertSame(
            substr($sha256, 0, 2) . '/' . substr($sha256, -2) . '/' . str_repeat('a', 32),
            $storage->storagePath(str_repeat('a', 32), $sha256),
        );
    }

    /**
     * store/chunks/delete.
     *
     * @return void
     */
    public function testStoreReadDelete(): void
    {
        $storage = new LocalFileStorage($this->rootPath);
        $sha256 = $storage->hash($this->sourcePath);
        $storagePath = $storage->storagePath(str_repeat('b', 32), $sha256);
        $expected = (string) file_get_contents($this->sourcePath);
        $storage->store($this->sourcePath, $storagePath);
        self::assertFileExists($this->rootPath . '/' . $storagePath);
        self::assertSame($expected, implode('', iterator_to_array($storage->chunks($storagePath, 4), false)));
        $storage->delete($storagePath);
        self::assertFileDoesNotExist($this->rootPath . '/' . $storagePath);
        $storage->delete($storagePath);
        self::assertTrue(true);
    }

    /**
     * exportTo копирует байты шарда во внешний файл.
     *
     * @return void
     */
    public function testExportToTarget(): void
    {
        $storage = new LocalFileStorage($this->rootPath);
        $sha256 = $storage->hash($this->sourcePath);
        $expected = (string) file_get_contents($this->sourcePath);
        $storagePath = $storage->storagePath(str_repeat('d', 32), $sha256);
        $storage->store($this->sourcePath, $storagePath);
        $target = sys_get_temp_dir() . '/opencode-export-' . bin2hex(random_bytes(4));

        try {
            $storage->exportTo($storagePath, $target);
            self::assertSame($expected, (string) file_get_contents($target));
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }

        $this->expectException(FileStorageException::class);
        $storage->exportTo($storagePath . '-absent', $target);
    }

    /**
     * Путь вне корня запрещён.
     *
     * @return void
     */
    public function testRejectsPathTraversal(): void
    {
        $storage = new LocalFileStorage($this->rootPath);
        foreach (['../evil', '/etc/passwd', ''] as $badPath) {
            try {
                iterator_to_array($storage->chunks($badPath, 10), false);
                self::fail('bad path must fail: ' . $badPath);
            } catch (FileStorageException $exception) {
                self::assertSame('FILE_STORAGE', $exception->getErrorCode());
            }
        }
    }

    /**
     * Некорректный ключ шарда.
     *
     * @return void
     */
    public function testRejectsInvalidStorageKey(): void
    {
        $storage = new LocalFileStorage($this->rootPath);
        try {
            $storage->storagePath('zzz', str_repeat('a', 64));
            self::fail('invalid public id must fail');
        } catch (FileStorageException $exception) {
            self::assertSame('FILE_STORAGE', $exception->getErrorCode());
        }
    }
}
