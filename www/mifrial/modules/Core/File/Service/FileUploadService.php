<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\FileSettings;
use Mifrial\Core\File\Dto\IncomingUpload;
use Mifrial\Core\File\Dto\NewFile;
use Mifrial\Core\File\Exception\FileImageInvalidException;
use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\File\Exception\FileMimeInvalidException;
use Mifrial\Core\File\Exception\FileStorageException;
use Mifrial\Core\File\Exception\FileTooLargeException;
use Mifrial\Core\File\Exception\FileUploadBrokenException;
use Mifrial\Core\File\Interface\Service\IFileStorage;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Throwable;

/**
 * Приём multipart-файла: проверка, диск, строка.
 */
final class FileUploadService
{
    private const MAX_IMAGE_SIDE = 20000;

    private const PUBLIC_ID_ATTEMPTS = 5;

    /**
     * Создаёт сервис загрузки.
     *
     * @param IUserAccess $userAccess Guard.
     * @param FileRepository $fileRepository Строки.
     * @param IFileStorage $fileStorage Диск.
     * @param FileSettings $fileSettings Настройки.
     * @param FileViewAssembler $fileViewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly FileRepository $fileRepository,
        private readonly IFileStorage $fileStorage,
        private readonly FileSettings $fileSettings,
        private readonly FileViewAssembler $fileViewAssembler,
    ) {
    }

    /**
     * Принимает файл актора и возвращает JSON-вид строки.
     *
     * @param IncomingUpload $incomingUpload Вход multipart.
     *
     * @return array<string, mixed> FileEntry.
     *
     * @throws FileInvalidException Если файл или имя недопустимы.
     * @throws FileTooLargeException Если размер больше лимита.
     * @throws FileMimeInvalidException Если тип вне политики.
     * @throws FileImageInvalidException Если картинка не читается.
     * @throws FileUploadBrokenException Если PHP не принял файл.
     */
    public function upload(IncomingUpload $incomingUpload): array
    {
        $requestActor = $this->userAccess->requireActor();
        $temporaryPath = $this->acceptedTemporaryPath($incomingUpload);
        [$sourcePath, $size] = $this->acceptedSource($temporaryPath);
        $mime = $this->acceptedMime($sourcePath);
        [$width, $height] = $this->imageDimensions($sourcePath, $mime);
        $originalName = $this->normalizeName($incomingUpload->getOriginalName());
        $sha256 = $this->fileStorage->hash($sourcePath);
        $publicId = $this->uniquePublicId();
        $storagePath = $this->fileStorage->storagePath($publicId, $sha256);
        $this->fileStorage->store($sourcePath, $storagePath);
        $fileId = $this->insertRow(
            $requestActor->getUserId(),
            $publicId,
            $originalName,
            $mime,
            $size,
            $sha256,
            $storagePath,
            $width,
            $height,
        );

        return $this->fileViewAssembler->assemble($this->fileRepository->getById($fileId));
    }

    /**
     * Проверяет код UPLOAD_ERR_* и возвращает tmp.
     *
     * @param IncomingUpload $incomingUpload Вход.
     *
     * @return string Временный путь.
     */
    private function acceptedTemporaryPath(IncomingUpload $incomingUpload): string
    {
        $uploadError = $incomingUpload->getUploadError();
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            if ($incomingUpload->getContentLength() > 0) {
                if ($incomingUpload->getContentLength() > $this->fileSettings->maxSize()) {
                    throw new FileTooLargeException($this->fileSettings->maxSize());
                }

                throw new FileUploadBrokenException('PHP dropped the upload body');
            }

            throw new FileInvalidException('No file uploaded');
        }

        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new FileTooLargeException($this->fileSettings->maxSize());
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new FileUploadBrokenException('PHP upload error: ' . $uploadError);
        }

        $temporaryPath = $incomingUpload->getTemporaryPath();
        if ($temporaryPath === null) {
            throw new FileUploadBrokenException('Uploaded file has no temporary path');
        }

        return $temporaryPath;
    }

    /**
     * Проверяет наличие и размер принятого файла.
     *
     * @param string $temporaryPath Временный путь.
     *
     * @return array{0: string, 1: int} Путь и фактический размер.
     */
    private function acceptedSource(string $temporaryPath): array
    {
        if (!is_file($temporaryPath)) {
            throw new FileUploadBrokenException('Uploaded file is missing');
        }

        $size = filesize($temporaryPath);
        if (!is_int($size) || $size < 1) {
            throw new FileUploadBrokenException('Uploaded file is empty');
        }

        if ($size > $this->fileSettings->maxSize()) {
            throw new FileTooLargeException($this->fileSettings->maxSize());
        }

        return [$temporaryPath, $size];
    }

    /**
     * Серверный mime и политика хранилища.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return string Mime.
     */
    private function acceptedMime(string $sourcePath): string
    {
        $mime = $this->fileStorage->mimeType($sourcePath);
        if (!$this->fileSettings->allowsMime($mime)) {
            throw new FileMimeInvalidException($this->fileSettings->mime());
        }

        return $mime;
    }

    /**
     * Размеры картинки.
     *
     * @param string $sourcePath Абсолютный путь.
     * @param string $mime Серверный тип.
     *
     * @return array{0: int|null, 1: int|null} Ширина и высота.
     */
    private function imageDimensions(string $sourcePath, string $mime): array
    {
        if (!str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $imageSize = $this->fileStorage->imageSize($sourcePath);
        if ($imageSize === null) {
            throw new FileImageInvalidException('File is not a readable image');
        }

        [$width, $height] = $imageSize;
        if ($width < 1 || $height < 1 || $width > self::MAX_IMAGE_SIDE || $height > self::MAX_IMAGE_SIDE) {
            throw new FileImageInvalidException('File image dimensions are out of bounds');
        }

        return [$width, $height];
    }

    /**
     * Имя клиента без пути и управляющих символов.
     *
     * @param string $originalName Имя из multipart.
     *
     * @return string Имя 1..255 символов.
     */
    private function normalizeName(string $originalName): string
    {
        $baseName = basename(str_replace('\\', '/', $originalName));
        $cleanName = preg_replace('/[\x00-\x1F\x7F]/u', '', $baseName);
        $trimmed = trim(is_string($cleanName) ? $cleanName : '');
        if ($trimmed === '') {
            throw new FileInvalidException('File name is empty');
        }

        return mb_strlen($trimmed) > 255 ? mb_substr($trimmed, 0, 255) : $trimmed;
    }

    /**
     * Уникальный public_id до вставки.
     *
     * @return string 32 hex.
     */
    private function uniquePublicId(): string
    {
        for ($attempt = 0; $attempt < self::PUBLIC_ID_ATTEMPTS; $attempt++) {
            $publicId = bin2hex(random_bytes(16));
            if ($this->fileRepository->findByPublicId($publicId) === null) {
                return $publicId;
            }
        }

        throw new FileStorageException('Cannot generate a unique file public id');
    }

    /**
     * Вставляет строку; при сбое удаляет уже записанные байты.
     *
     * @param int $ownerId Владелец.
     * @param string $publicId Внешний id.
     * @param string $originalName Имя.
     * @param string $mime Тип.
     * @param int $size Байт.
     * @param string $sha256 Хеш.
     * @param string $storagePath Путь.
     * @param int|null $width Ширина.
     * @param int|null $height Высота.
     *
     * @return int Id строки.
     */
    private function insertRow(
        int $ownerId,
        string $publicId,
        string $originalName,
        string $mime,
        int $size,
        string $sha256,
        string $storagePath,
        ?int $width,
        ?int $height,
    ): int {
        try {
            return $this->fileRepository->add(NewFile::fromNormalized([
                'public_id' => $publicId,
                'owner_id' => $ownerId,
                'original_name' => $originalName,
                'mime' => $mime,
                'size' => $size,
                'sha256' => $sha256,
                'storage_path' => $storagePath,
                'width' => $width,
                'height' => $height,
                'is_archived' => false,
            ]));
        } catch (Throwable $throwable) {
            try {
                $this->fileStorage->delete($storagePath);
            } catch (Throwable $cleanupFailure) {
                unset($cleanupFailure);
            }

            throw $throwable;
        }
    }
}
