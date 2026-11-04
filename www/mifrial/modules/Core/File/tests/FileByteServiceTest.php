<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use LogicException;
use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Exception\FileNotFoundException;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\File\Service\FileByteService;
use Mifrial\Core\File\Tests\Fixture\ByteCapture;
use Mifrial\Core\File\Tests\Fixture\FakeFileStorage;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\DateTime;
use PHPUnit\Framework\TestCase;

final class FileByteServiceTest extends TestCase
{
    /**
     * Нет актора — 403 и no-store.
     *
     * @return void
     */
    public function testNoActorIs403(): void
    {
        $capture = $this->emitWith(
            $this->filesThatThrow(new ActionException('AUTH_REQUIRED', 'Authentication is required')),
        );

        self::assertSame(403, $capture->statusCode);
        self::assertSame('', $capture->body);
        self::assertContains('Cache-Control: no-store', $capture->headers);
    }

    /**
     * Строки нет — 404.
     *
     * @return void
     */
    public function testMissingRowIs404(): void
    {
        $capture = $this->emitWith($this->filesThatThrow(new FileNotFoundException()));

        self::assertSame(404, $capture->statusCode);
        self::assertContains('Cache-Control: no-store', $capture->headers);
    }

    /**
     * Архивная строка — 404 без обращения к диску.
     *
     * @return void
     */
    public function testArchivedRowIs404(): void
    {
        $capture = new ByteCapture();
        $service = new FileByteService(
            $this->filesReturning($this->fileRecord(true)),
            new FakeFileStorage(),
            $capture->emitter(),
        );

        $service->emit(str_repeat('a', 32), null);

        self::assertSame(404, $capture->statusCode);
        self::assertSame('', $capture->body);
    }

    /**
     * Активная строка — байты и ETag.
     *
     * @return void
     */
    public function testActiveRowIsEmitted(): void
    {
        $fileRecord = $this->fileRecord(false);
        $storage = new FakeFileStorage();
        $storage->addSource('/tmp/source.png', 'abcdef', 'image/png');
        $storage->store('/tmp/source.png', $fileRecord->getStoragePath());
        $capture = new ByteCapture();
        (new FileByteService($this->filesReturning($fileRecord), $storage, $capture->emitter()))
            ->emit($fileRecord->getPublicId(), null);

        self::assertSame(200, $capture->statusCode);
        self::assertSame('abcdef', $capture->body);
        self::assertContains('ETag: "' . $fileRecord->getSha256() . '"', $capture->headers);
    }

    /**
     * Прогоняет сервис через приёмник.
     *
     * @param IFiles $files Порт.
     *
     * @return ByteCapture Приёмник.
     */
    private function emitWith(IFiles $files): ByteCapture
    {
        $capture = new ByteCapture();
        (new FileByteService($files, new FakeFileStorage(), $capture->emitter()))
            ->emit(str_repeat('a', 32), null);

        return $capture;
    }

    /**
     * Порт, который отбивает чтение.
     *
     * @param ActionException $exception Отказ.
     *
     * @return IFiles Порт.
     */
    private function filesThatThrow(ActionException $exception): IFiles
    {
        return new class ($exception) implements IFiles {
            /**
             * Создаёт порт с заданным отказом.
             *
             * @param ActionException $exception Отказ.
             *
             * @return void
             */
            public function __construct(private readonly ActionException $exception)
            {
            }

            /**
             * Бросает заданный отказ.
             *
             * @param string $publicId Внешний id.
             *
             * @return FileRecord Не возвращается.
             */
            public function requireReadable(string $publicId): FileRecord
            {
                throw $this->exception;
            }

            /**
             * Не используется.
             *
             * @param string $publicId Внешний id.
             *
             * @return FileRecord Не возвращается.
             */
            public function requireOwned(string $publicId): FileRecord
            {
                throw new LogicException('requireOwned must not be used');
            }

            /**
             * Не используется.
             *
             * @param string $publicId Внешний id.
             *
             * @return void Не возвращается.
             */
            public function archive(string $publicId): void
            {
                throw new LogicException('archive must not be used');
            }

            /**
             * Не используется.
             *
             * @param string $publicId Внешний id.
             * @param string $targetPath Назначение.
             *
             * @return FileRecord Не возвращается.
             */
            public function copyOwnedTo(string $publicId, string $targetPath): FileRecord
            {
                throw new LogicException('copyOwnedTo must not be used');
            }

            /**
             * Не используется.
             *
             * @param string $sourcePublicId Внешний id.
             * @param string $sourcePath Байты.
             * @param string $originalName Имя.
             *
             * @return FileRecord Не возвращается.
             */
            public function storeDerived(string $sourcePublicId, string $sourcePath, string $originalName): FileRecord
            {
                throw new LogicException('storeDerived must not be used');
            }

            /**
             * Не используется.
             *
             * @param array<int, int> $fileIds Id.
             *
             * @return array<int, string> Не возвращается.
             */
            public function activePublicIds(array $fileIds): array
            {
                throw new LogicException('activePublicIds must not be used');
            }
        };
    }

    /**
     * Порт, который отдаёт запись.
     *
     * @param FileRecord $fileRecord Запись.
     *
     * @return IFiles Порт.
     */
    private function filesReturning(FileRecord $fileRecord): IFiles
    {
        return new class ($fileRecord) implements IFiles {
            /**
             * Создаёт порт с заданной записью.
             *
             * @param FileRecord $fileRecord Запись.
             *
             * @return void
             */
            public function __construct(private readonly FileRecord $fileRecord)
            {
            }

            /**
             * Возвращает заданную запись.
             *
             * @param string $publicId Внешний id.
             *
             * @return FileRecord Запись.
             */
            public function requireReadable(string $publicId): FileRecord
            {
                return $this->fileRecord;
            }

            /**
             * Не используется.
             *
             * @param string $publicId Внешний id.
             *
             * @return FileRecord Не возвращается.
             */
            public function requireOwned(string $publicId): FileRecord
            {
                throw new LogicException('requireOwned must not be used');
            }

            /**
             * Не используется.
             *
             * @param string $publicId Внешний id.
             *
             * @return void Не возвращается.
             */
            public function archive(string $publicId): void
            {
                throw new LogicException('archive must not be used');
            }

            /**
             * Не используется.
             *
             * @param string $publicId Внешний id.
             * @param string $targetPath Назначение.
             *
             * @return FileRecord Не возвращается.
             */
            public function copyOwnedTo(string $publicId, string $targetPath): FileRecord
            {
                throw new LogicException('copyOwnedTo must not be used');
            }

            /**
             * Не используется.
             *
             * @param string $sourcePublicId Внешний id.
             * @param string $sourcePath Байты.
             * @param string $originalName Имя.
             *
             * @return FileRecord Не возвращается.
             */
            public function storeDerived(string $sourcePublicId, string $sourcePath, string $originalName): FileRecord
            {
                throw new LogicException('storeDerived must not be used');
            }

            /**
             * Не используется.
             *
             * @param array<int, int> $fileIds Id.
             *
             * @return array<int, string> Не возвращается.
             */
            public function activePublicIds(array $fileIds): array
            {
                throw new LogicException('activePublicIds must not be used');
            }
        };
    }

    /**
     * Запись файла для сценария.
     *
     * @param bool $isArchived Архив.
     *
     * @return FileRecord Запись.
     */
    private function fileRecord(bool $isArchived): FileRecord
    {
        $sha256 = hash('sha256', 'abcdef');

        return FileRecord::fromNormalized([
            'id' => 1,
            'public_id' => str_repeat('a', 32),
            'owner_id' => 1,
            'original_name' => 'cover.png',
            'mime' => 'image/png',
            'size' => 6,
            'sha256' => $sha256,
            'storage_path' => substr($sha256, 0, 2) . '/' . substr($sha256, -2) . '/' . str_repeat('a', 32),
            'width' => null,
            'height' => null,
            'is_archived' => $isArchived,
            'archived_at' => $isArchived ? DateTime::fromUnix(2) : null,
            'created_at' => DateTime::fromUnix(1),
        ]);
    }
}
