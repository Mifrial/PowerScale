<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Tests\Fixture\ByteCapture;
use Mifrial\Core\File\Tests\Fixture\FakeFileStorage;
use Mifrial\Core\Kernel\Value\DateTime;
use PHPUnit\Framework\TestCase;

final class FileByteEmitterTest extends TestCase
{
    /**
     * 200: заголовки кеша, ETag, длина и байты.
     *
     * @return void
     */
    public function testEmitsBytesAndHeaders(): void
    {
        $fileRecord = $this->fileRecord('image/png', 'cover name.png', 6);
        $storage = new FakeFileStorage();
        $storage->addSource('/tmp/source.png', 'abcdef', 'image/png');
        $storage->store('/tmp/source.png', $fileRecord->getStoragePath());
        $capture = new ByteCapture();
        $capture->emitter()->emit($fileRecord, $storage, null);
        self::assertSame(200, $capture->statusCode);
        self::assertSame('abcdef', $capture->body);
        self::assertContains('ETag: "' . $fileRecord->getSha256() . '"', $capture->headers);
        self::assertContains('Content-Type: image/png', $capture->headers);
        self::assertContains('Content-Length: 6', $capture->headers);
        self::assertContains('Cache-Control: private, max-age=31536000, immutable', $capture->headers);
        self::assertContains("Content-Disposition: inline; filename*=UTF-8''cover%20name.png", $capture->headers);
    }

    /**
     * If-None-Match совпал — 304 без тела.
     *
     * @return void
     */
    public function testNotModified(): void
    {
        $fileRecord = $this->fileRecord('image/png', 'cover.png', 6);
        $storage = new FakeFileStorage();
        $capture = new ByteCapture();
        $capture->emitter()->emit($fileRecord, $storage, '"' . $fileRecord->getSha256() . '"');
        self::assertSame(304, $capture->statusCode);
        self::assertSame('', $capture->body);
        self::assertNotContains('Content-Length: 6', $capture->headers);

        $weakCapture = new ByteCapture();
        $weakCapture->emitter()->emit($fileRecord, $storage, 'W/"' . $fileRecord->getSha256() . '"');
        self::assertSame(304, $weakCapture->statusCode);
    }

    /**
     * Ошибка — голый статус и no-store.
     *
     * @return void
     */
    public function testErrorStatus(): void
    {
        $capture = new ByteCapture();
        $capture->emitter()->emitError(404);
        self::assertSame(404, $capture->statusCode);
        self::assertSame('', $capture->body);
        self::assertContains('Cache-Control: no-store', $capture->headers);
    }

    /**
     * Запись с фиксированным sha.
     *
     * @param string $mime Тип.
     * @param string $name Имя.
     * @param int $size Байт.
     *
     * @return FileRecord Запись.
     */
    private function fileRecord(string $mime, string $name, int $size): FileRecord
    {
        $sha256 = hash('sha256', 'abcdef');

        return FileRecord::fromNormalized([
            'id' => 1,
            'public_id' => str_repeat('a', 32),
            'owner_id' => 1,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
            'sha256' => $sha256,
            'storage_path' => substr($sha256, 0, 2) . '/' . substr($sha256, -2) . '/' . str_repeat('a', 32),
            'width' => null,
            'height' => null,
            'is_archived' => false,
            'archived_at' => null,
            'created_at' => DateTime::fromUnix(1),
        ]);
    }
}
