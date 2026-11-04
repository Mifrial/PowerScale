<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры полной строки файла; параметры ctor — поля, не порты.

namespace Mifrial\Core\File\Dto;

use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка файла.
 */
final class FileRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Внутренний id.
     * @param string $publicId Внешний id.
     * @param int $ownerId Владелец.
     * @param string $originalName Имя клиента.
     * @param string $mime Серверный тип.
     * @param int $size Байт.
     * @param string $sha256 Хеш.
     * @param string $storagePath Путь относительно корня.
     * @param int|null $width Ширина картинки.
     * @param int|null $height Высота картинки.
     * @param bool $isArchived Архив.
     * @param DateTime|null $archivedAt Момент архива.
     * @param DateTime $createdAt Момент загрузки.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly int $ownerId,
        private readonly string $originalName,
        private readonly string $mime,
        private readonly int $size,
        private readonly string $sha256,
        private readonly string $storagePath,
        private readonly ?int $width,
        private readonly ?int $height,
        private readonly bool $isArchived,
        private readonly ?DateTime $archivedAt,
        private readonly DateTime $createdAt,
    ) {
    }

    /**
     * Собирает Record из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     *
     * @throws FileInvalidException Если обязательные поля неверны.
     */
    public static function fromNormalized(array $fields): self
    {
        $id = $fields['id'] ?? null;
        $publicId = $fields['public_id'] ?? null;
        $ownerId = $fields['owner_id'] ?? null;
        $originalName = $fields['original_name'] ?? null;
        $mime = $fields['mime'] ?? null;
        $size = $fields['size'] ?? null;
        $sha256 = $fields['sha256'] ?? null;
        $storagePath = $fields['storage_path'] ?? null;
        $isArchived = $fields['is_archived'] ?? null;
        $createdAt = $fields['created_at'] ?? null;
        if (
            !is_int($id)
            || !is_string($publicId)
            || !is_int($ownerId)
            || !is_string($originalName)
            || !is_string($mime)
            || !is_int($size)
            || !is_string($sha256)
            || !is_string($storagePath)
            || !is_bool($isArchived)
            || !$createdAt instanceof DateTime
        ) {
            throw new FileInvalidException('File record is incomplete');
        }

        return new self(
            $id,
            $publicId,
            $ownerId,
            $originalName,
            $mime,
            $size,
            $sha256,
            $storagePath,
            self::optionalInt($fields['width'] ?? null),
            self::optionalInt($fields['height'] ?? null),
            $isArchived,
            self::optionalDateTime($fields['archived_at'] ?? null),
            $createdAt,
        );
    }

    /**
     * Внутренний id.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Внешний id.
     *
     * @return string 32 hex.
     */
    public function getPublicId(): string
    {
        return $this->publicId;
    }

    /**
     * Владелец.
     *
     * @return int Id учётки.
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * Имя от клиента.
     *
     * @return string Имя.
     */
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Серверный тип.
     *
     * @return string Mime.
     */
    public function getMime(): string
    {
        return $this->mime;
    }

    /**
     * Размер.
     *
     * @return int Байт.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * SHA-256.
     *
     * @return string Hex.
     */
    public function getSha256(): string
    {
        return $this->sha256;
    }

    /**
     * Путь на диске относительно корня.
     *
     * @return string Путь.
     */
    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    /**
     * Ширина картинки.
     *
     * @return int|null Пиксели или null.
     */
    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * Высота картинки.
     *
     * @return int|null Пиксели или null.
     */
    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * Архив.
     *
     * @return bool true, если архивный.
     */
    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    /**
     * Момент архива.
     *
     * @return DateTime|null Время или null.
     */
    public function getArchivedAt(): ?DateTime
    {
        return $this->archivedAt;
    }

    /**
     * Момент загрузки.
     *
     * @return DateTime Время.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Необязательный int.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Число или null.
     */
    private static function optionalInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * Необязательная дата.
     *
     * @param mixed $value Значение.
     *
     * @return DateTime|null Время или null.
     */
    private static function optionalDateTime(mixed $value): ?DateTime
    {
        return $value instanceof DateTime ? $value : null;
    }
}
