<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Service\FileViewAssembler;
use Mifrial\Core\Kernel\Value\DateTime;
use PHPUnit\Framework\TestCase;

final class FileViewAssemblerTest extends TestCase
{
    /**
     * JSON-вид без storagePath, даты — unix.
     *
     * @return void
     */
    public function testAssembleWithoutStoragePath(): void
    {
        $fileRecord = FileRecord::fromNormalized([
            'id' => 7,
            'public_id' => str_repeat('a', 32),
            'owner_id' => 3,
            'original_name' => 'cover.png',
            'mime' => 'image/png',
            'size' => 1234,
            'sha256' => str_repeat('b', 64),
            'storage_path' => 'aa/bb/' . str_repeat('a', 32),
            'width' => 800,
            'height' => 600,
            'is_archived' => true,
            'archived_at' => DateTime::fromUnix(1700000000),
            'created_at' => DateTime::fromUnix(1690000000),
        ]);
        $view = (new FileViewAssembler())->assemble($fileRecord);
        self::assertSame(7, $view['id']);
        self::assertSame(3, $view['ownerId']);
        self::assertSame('cover.png', $view['originalName']);
        self::assertSame(800, $view['width']);
        self::assertSame(600, $view['height']);
        self::assertTrue($view['isArchived']);
        self::assertFalse($view['isDerived']);
        self::assertSame(1700000000, $view['archivedAt']);
        self::assertSame(1690000000, $view['createdAt']);
        self::assertArrayNotHasKey('storagePath', $view);
    }

    /**
     * Необязательные поля — null.
     *
     * @return void
     */
    public function testOptionalFieldsAreNull(): void
    {
        $fileRecord = FileRecord::fromNormalized([
            'id' => 1,
            'public_id' => str_repeat('c', 32),
            'owner_id' => 2,
            'original_name' => 'doc.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'sha256' => str_repeat('d', 64),
            'storage_path' => 'cc/dd/' . str_repeat('c', 32),
            'width' => null,
            'height' => null,
            'is_archived' => false,
            'archived_at' => null,
            'created_at' => DateTime::fromUnix(1),
        ]);
        $view = (new FileViewAssembler())->assemble($fileRecord);
        self::assertNull($view['width']);
        self::assertNull($view['height']);
        self::assertNull($view['archivedAt']);
        self::assertFalse($view['isArchived']);
    }
}
