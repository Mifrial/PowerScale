<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
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
     * @param bool $isDerived Производная соседа.
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
        private readonly bool $isDerived,
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
        self::assertShape($fields);
        /** @var DateTime $createdAt */
        $createdAt = $fields['created_at'];

        return new self(
            (int) $fields['id'],
            (string) $fields['public_id'],
            (int) $fields['owner_id'],
            (string) $fields['original_name'],
            (string) $fields['mime'],
            (int) $fields['size'],
            (string) $fields['sha256'],
            (string) $fields['storage_path'],
            self::optionalInt($fields['width'] ?? null),
            self::optionalInt($fields['height'] ?? null),
            (bool) $fields['is_archived'],
            (bool) ($fields['is_derived'] ?? false),
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
     * Хеш SHA-256.
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
     * Производная соседа (превью, аватар), не пользовательский оригинал.
     *
     * @return bool true, если файл создан storeDerived.
     */
    public function isDerived(): bool
    {
        return $this->isDerived;
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
     * Проверяет обязательные поля строки.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return void
     *
     * @throws FileInvalidException Если поле отсутствует или неверного типа.
     */
    private static function assertShape(array $fields): void
    {
        self::assertIntFields($fields);
        self::assertTextFields($fields);
        self::assertStorageFields($fields);
        self::assertBoolFields($fields);
        if (!(($fields['created_at'] ?? null) instanceof DateTime)) {
            throw new FileInvalidException('File record has no created_at');
        }
    }

    /**
     * Проверяет целочисленные поля.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return void
     *
     * @throws FileInvalidException Если поле не int.
     */
    private static function assertIntFields(array $fields): void
    {
        if (!is_int($fields['id'] ?? null) || !is_int($fields['owner_id'] ?? null)) {
            throw new FileInvalidException('File record has no id');
        }

        if (!is_int($fields['size'] ?? null)) {
            throw new FileInvalidException('File record has no size');
        }
    }

    /**
     * Проверяет текстовые поля.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return void
     *
     * @throws FileInvalidException Если поле не string.
     */
    private static function assertTextFields(array $fields): void
    {
        if (
            !is_string($fields['public_id'] ?? null)
            || !is_string($fields['original_name'] ?? null)
            || !is_string($fields['mime'] ?? null)
        ) {
            throw new FileInvalidException('File record has no text fields');
        }
    }

    /**
     * Проверяет поля хранения.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return void
     *
     * @throws FileInvalidException Если поле неверного типа.
     */
    private static function assertStorageFields(array $fields): void
    {
        if (
            !is_string($fields['sha256'] ?? null)
            || !is_string($fields['storage_path'] ?? null)
            || !is_bool($fields['is_derived'] ?? false)
        ) {
            throw new FileInvalidException('File record has no storage fields');
        }
    }

    /**
     * Проверяет булевы поля.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return void
     *
     * @throws FileInvalidException Если поле не bool.
     */
    private static function assertBoolFields(array $fields): void
    {
        if (!is_bool($fields['is_archived'] ?? null)) {
            throw new FileInvalidException('File record has no archive flag');
        }
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
