<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Dto\FileSettings;
use Mifrial\Core\File\Dto\IncomingUpload;
use Mifrial\Core\File\Exception\FileImageInvalidException;
use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\File\Exception\FileMimeInvalidException;
use Mifrial\Core\File\Exception\FileTooLargeException;
use Mifrial\Core\File\Exception\FileUploadBrokenException;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\File\Service\FileIntake;
use Mifrial\Core\File\Service\FileUploadService;
use Mifrial\Core\File\Service\FileViewAssembler;
use Mifrial\Core\File\Tests\Fixture\FakeFileStorage;
use Mifrial\Core\File\Tests\Fixture\FakeUserAccess;
use Mifrial\Core\File\Tests\Fixture\UnusedOpenedRecords;
use Mifrial\Core\Kernel\Dto\RequestActor;
use PHPUnit\Framework\TestCase;

final class FileUploadServiceTest extends TestCase
{
    private string $sourcePath = '';

    /**
     * Пишет принятый файл во временный каталог.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->sourcePath = (string) tempnam(sys_get_temp_dir(), 'opencode-upload-');
        file_put_contents($this->sourcePath, 'png-bytes');
    }

    /**
     * Убирает временный файл.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (is_file($this->sourcePath)) {
            unlink($this->sourcePath);
        }
    }

    /**
     * PHP отбил размер — FILE_TOO_LARGE с лимитом.
     *
     * @return void
     */
    public function testIniSizeIsTooLarge(): void
    {
        $service = $this->service(new FakeFileStorage(), 10, ['image/png']);
        try {
            $service->upload(IncomingUpload::fromFileMap([
                'file' => [
                    'tmp_name' => $this->sourcePath,
                    'name' => 'cover.png',
                    'size' => 9,
                    'error' => UPLOAD_ERR_INI_SIZE,
                    'type' => 'image/png',
                ],
            ], 9));
            self::fail('ini size must fail');
        } catch (FileTooLargeException $exception) {
            self::assertSame('FILE_TOO_LARGE', $exception->getErrorCode());
        }
    }

    /**
     * Пустой $_FILES при непустом теле — либо лимит, либо битый upload.
     *
     * @return void
     */
    public function testDroppedBody(): void
    {
        $service = $this->service(new FakeFileStorage(), 10, ['image/png']);
        try {
            $service->upload(IncomingUpload::fromFileMap([], 100));
            self::fail('dropped body over the limit must fail');
        } catch (FileTooLargeException $exception) {
            self::assertSame('FILE_TOO_LARGE', $exception->getErrorCode());
            self::assertSame(['maxSize' => 10], $exception->getErrorDetails());
        }

        try {
            $service->upload(IncomingUpload::fromFileMap([], 5));
            self::fail('dropped body must fail');
        } catch (FileUploadBrokenException $exception) {
            self::assertSame('FILE_UPLOAD_BROKEN', $exception->getErrorCode());
        }

        try {
            $service->upload(IncomingUpload::fromFileMap([], 0));
            self::fail('missing file must fail');
        } catch (FileInvalidException $exception) {
            self::assertSame('FILE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Битый upload без временного файла.
     *
     * @return void
     */
    public function testMissingTemporaryFile(): void
    {
        $service = $this->service(new FakeFileStorage(), 1000, ['image/png']);
        try {
            $service->upload($this->incoming('/tmp/does-not-exist-' . bin2hex(random_bytes(4))));
            self::fail('missing temp file must fail');
        } catch (FileUploadBrokenException $exception) {
            self::assertSame('FILE_UPLOAD_BROKEN', $exception->getErrorCode());
        }
    }

    /**
     * Тип вне политики.
     *
     * @return void
     */
    public function testMimeOutsidePolicy(): void
    {
        $storage = new FakeFileStorage();
        $storage->addSource($this->sourcePath, 'png-bytes', 'text/plain');
        $service = $this->service($storage, 1000, ['image/png']);
        try {
            $service->upload($this->incoming($this->sourcePath));
            self::fail('disallowed mime must fail');
        } catch (FileMimeInvalidException $exception) {
            self::assertSame('FILE_MIME_INVALID', $exception->getErrorCode());
            self::assertSame(['allowedMime' => ['image/png']], $exception->getErrorDetails());
        }
    }

    /**
     * Картинка без читаемых размеров.
     *
     * @return void
     */
    public function testUnreadableImage(): void
    {
        $storage = new FakeFileStorage();
        $storage->addSource($this->sourcePath, 'png-bytes', 'image/png', null);
        $service = $this->service($storage, 1000, ['image/png']);
        try {
            $service->upload($this->incoming($this->sourcePath));
            self::fail('unreadable image must fail');
        } catch (FileImageInvalidException $exception) {
            self::assertSame('FILE_IMAGE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустое имя клиента.
     *
     * @return void
     */
    public function testEmptyName(): void
    {
        $storage = new FakeFileStorage();
        $storage->addSource($this->sourcePath, 'png-bytes', 'image/png', [1, 1]);
        $service = $this->service($storage, 1000, ['image/png']);
        try {
            $service->upload($this->incoming($this->sourcePath, '   '));
            self::fail('empty name must fail');
        } catch (FileInvalidException $exception) {
            self::assertSame('FILE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Сервис на пустом репозитории.
     *
     * @param FakeFileStorage $storage Хранилище.
     * @param int $maxSize Лимит.
     * @param array<int, string> $mime Политика.
     *
     * @return FileUploadService Сервис.
     */
    private function service(FakeFileStorage $storage, int $maxSize, array $mime): FileUploadService
    {
        $settings = FileSettings::fromSection([
            'path' => sys_get_temp_dir(),
            'max_size' => $maxSize,
            'mime' => $mime,
        ], sys_get_temp_dir());

        return new FileUploadService(
            new FakeUserAccess(new RequestActor(1, [], false)),
            new FileIntake(new FileRepository(new UnusedOpenedRecords()), $storage, $settings),
            new FileViewAssembler(),
        );
    }

    /**
     * Вход принятого файла.
     *
     * @param string $temporaryPath Временный путь.
     * @param string $name Имя.
     *
     * @return IncomingUpload Вход.
     */
    private function incoming(string $temporaryPath, string $name = 'cover.png'): IncomingUpload
    {
        return IncomingUpload::fromFileMap([
            'file' => [
                'tmp_name' => $temporaryPath,
                'name' => $name,
                'size' => 9,
                'error' => UPLOAD_ERR_OK,
                'type' => 'image/png',
            ],
        ], 9);
    }
}
