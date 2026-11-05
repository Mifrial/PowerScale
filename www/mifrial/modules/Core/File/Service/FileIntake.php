<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Dto\FileSettings;
use Mifrial\Core\File\Dto\NewFile;
use Mifrial\Core\File\Exception\FileImageInvalidException;
use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\File\Exception\FileMimeInvalidException;
use Mifrial\Core\File\Exception\FileStorageException;
use Mifrial\Core\File\Exception\FileTooLargeException;
use Mifrial\Core\File\Interface\Service\IFileStorage;
use Mifrial\Core\File\Repository\FileRepository;
use Throwable;

/**
 * Приём готового локального файла: проверки, шард, строка.
 *
 * Общий путь для multipart-загрузки и производных, которые готовит сосед.
 */
final class FileIntake
{
    private const MAX_IMAGE_SIDE = 20000;

    private const PUBLIC_ID_ATTEMPTS = 5;

    /**
     * Создаёт приём файла.
     *
     * @param FileRepository $fileRepository Строки.
     * @param IFileStorage $fileStorage Диск.
     * @param FileSettings $fileSettings Настройки.
     *
     * @return void
     */
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly IFileStorage $fileStorage,
        private readonly FileSettings $fileSettings,
    ) {
    }

    /**
     * Переносит файл в шард и вставляет строку.
     *
     * Исходный файл после вызова освобождается: байты переносятся, а не копируются.
     *
     * @param int $ownerId Владелец.
     * @param string $sourcePath Абсолютный путь к локальному файлу.
     * @param string $originalName Имя для строки.
     *
     * @return FileRecord Вставленная строка.
     *
     * @throws FileInvalidException Если файл или имя недопустимы.
     * @throws FileTooLargeException Если размер больше лимита.
     * @throws FileMimeInvalidException Если тип вне политики.
     * @throws FileImageInvalidException Если картинка не читается.
     */
    public function insert(
        int $ownerId,
        string $sourcePath,
        string $originalName,
    ): FileRecord {
        return $this->intake($ownerId, $sourcePath, $originalName, false, true);
    }

    /**
     * Принимает готовую производную соседа: upload-политика не применяется.
     *
     * @param int $ownerId Владелец.
     * @param string $sourcePath Абсолютный путь к локальному файлу.
     * @param string $originalName Имя для строки.
     *
     * @return FileRecord Вставленная строка.
     *
     * @throws FileInvalidException Если файл или имя недопустимы.
     * @throws FileImageInvalidException Если картинка не читается.
     */
    public function insertDerived(
        int $ownerId,
        string $sourcePath,
        string $originalName,
    ): FileRecord {
        return $this->intake($ownerId, $sourcePath, $originalName, true, false);
    }

    /**
     * Общий потолок размера файла.
     *
     * @return int Байты.
     */
    public function maxSize(): int
    {
        return $this->fileSettings->maxSize();
    }

    /**
     * Общий приём: проверки, шард, строка.
     *
     * @param int $ownerId Владелец.
     * @param string $sourcePath Абсолютный путь к локальному файлу.
     * @param string $originalName Имя для строки.
     * @param bool $isDerived Производная соседа.
     * @param bool $enforceUploadPolicy Применять mime/size политику загрузки.
     *
     * @return FileRecord Вставленная строка.
     *
     * @throws FileInvalidException Если файл или имя недопустимы.
     * @throws FileTooLargeException Если размер больше лимита.
     * @throws FileMimeInvalidException Если тип вне политики.
     * @throws FileImageInvalidException Если картинка не читается.
     */
    private function intake(
        int $ownerId,
        string $sourcePath,
        string $originalName,
        bool $isDerived,
        bool $enforceUploadPolicy,
    ): FileRecord {
        $size = $this->sourceSize($sourcePath, $enforceUploadPolicy);
        $mime = $this->acceptedMime($sourcePath, $enforceUploadPolicy);
        [$width, $height] = $this->imageDimensions($sourcePath, $mime);
        $originalName = $this->normalizeName($originalName);
        $sha256 = $this->fileStorage->hash($sourcePath);
        $publicId = $this->uniquePublicId();
        $storagePath = $this->fileStorage->storagePath($publicId, $sha256);
        $newFile = $this->newFile(
            $ownerId,
            $publicId,
            $originalName,
            $mime,
            $size,
            $sha256,
            $storagePath,
            $width,
            $height,
            $isDerived,
        );
        $this->fileStorage->store($sourcePath, $storagePath);

        return $this->storeNewFile($newFile, $storagePath);
    }

    /**
     * Проверяет наличие и размер локального файла.
     *
     * @param string $sourcePath Абсолютный путь.
     * @param bool $enforceMaxSize Применять общий потолок загрузки.
     *
     * @return int Фактический размер в байтах.
     *
     * @throws FileTooLargeException Если размер больше лимита.
     * @throws FileInvalidException Если файл пуст.
     */
    private function sourceSize(string $sourcePath, bool $enforceMaxSize): int
    {
        if (!is_file($sourcePath)) {
            throw new FileInvalidException('Accepted file is missing');
        }

        $size = filesize($sourcePath);
        if (!is_int($size) || $size < 1) {
            throw new FileInvalidException('Accepted file is empty');
        }

        if ($enforceMaxSize && $size > $this->fileSettings->maxSize()) {
            throw new FileTooLargeException($this->fileSettings->maxSize());
        }

        return $size;
    }

    /**
     * Серверный mime и политика хранилища.
     *
     * @param string $sourcePath Абсолютный путь.
     * @param bool $enforcePolicy Применять список разрешённых типов.
     *
     * @return string Mime.
     *
     * @throws FileMimeInvalidException Если тип вне политики.
     */
    private function acceptedMime(string $sourcePath, bool $enforcePolicy): string
    {
        $mime = $this->fileStorage->mimeType($sourcePath);
        if ($enforcePolicy && !$this->fileSettings->allowsMime($mime)) {
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
     *
     * @throws FileImageInvalidException Если картинка не читается.
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
     * @param string $originalName Имя.
     *
     * @return string Имя 1..255 символов.
     *
     * @throws FileInvalidException Если имя пустое.
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
     *
     * @throws FileStorageException Если уникальный id не нашёлся.
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
     * @param NewFile $newFile Поля строки.
     * @param string $storagePath Путь шарда.
     *
     * @return FileRecord Вставленная строка.
     *
     * @throws Throwable Если строка не вставилась; байты при этом удаляются.
     */
    private function storeNewFile(NewFile $newFile, string $storagePath): FileRecord
    {
        try {
            $fileId = $this->fileRepository->add($newFile);
        } catch (Throwable $throwable) {
            $this->deleteStoredFile($storagePath);
            throw $throwable;
        }

        return $this->fileRepository->getById($fileId);
    }

    /**
     * Собирает NewFile для SmartTable.
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
     * @param bool $isDerived Производная.
     *
     * @return NewFile Поля новой строки.
     */
    private function newFile(
        int $ownerId,
        string $publicId,
        string $originalName,
        string $mime,
        int $size,
        string $sha256,
        string $storagePath,
        ?int $width,
        ?int $height,
        bool $isDerived,
    ): NewFile {
        return NewFile::fromNormalized([
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
            'is_derived' => $isDerived,
        ]);
    }

    /**
     * Убирает байты, если строка не записалась.
     *
     * @param string $storagePath Путь шарда.
     *
     * @return void
     */
    private function deleteStoredFile(string $storagePath): void
    {
        try {
            $this->fileStorage->delete($storagePath);
        } catch (Throwable $cleanupFailure) {
            unset($cleanupFailure);
        }
    }
}
