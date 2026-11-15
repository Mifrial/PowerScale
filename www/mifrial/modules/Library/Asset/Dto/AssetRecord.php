<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Library\Asset\Exception\AssetInvalidException;

/**
 * Прочитанная строка `asset_item`.
 */
final class AssetRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $collectionId Назначение.
     * @param int $fileId Файл в Core/File.
     * @param bool $isShared Общая часть.
     * @param int|null $ownerId Владелец личной части.
     * @param string $name Подпись в библиотеке.
     * @param array<string, mixed>|null $meta Данные потребителя.
     * @param bool $isArchived Архив.
     * @param DateTime $createdAt Момент добавления.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $collectionId,
        private readonly int $fileId,
        private readonly bool $isShared,
        private readonly ?int $ownerId,
        private readonly string $name,
        private readonly ?array $meta,
        private readonly bool $isArchived,
        private readonly DateTime $createdAt,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     *
     * @throws AssetInvalidException Если строка битая.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            AssetValues::requireInt($fields, 'id'),
            AssetValues::requireInt($fields, 'collection_id'),
            AssetValues::requireInt($fields, 'file_id'),
            AssetValues::requireBool($fields, 'is_shared'),
            AssetValues::optionalInt($fields, 'owner_id'),
            AssetValues::requireString($fields, 'name'),
            AssetValues::optionalArray($fields, 'meta'),
            AssetValues::requireBool($fields, 'is_archived'),
            AssetValues::requireDateTime($fields, 'created_at'),
        );
    }

    /**
     * Идентификатор.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Назначение.
     *
     * @return int Id коллекции.
     */
    public function getCollectionId(): int
    {
        return $this->collectionId;
    }

    /**
     * Файл.
     *
     * @return int Внутренний id файла.
     */
    public function getFileId(): int
    {
        return $this->fileId;
    }

    /**
     * Общая часть.
     *
     * @return bool Флаг.
     */
    public function isShared(): bool
    {
        return $this->isShared;
    }

    /**
     * Владелец личной части.
     *
     * @return int|null Id учётки или null у общей.
     */
    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    /**
     * Подпись.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Данные потребителя.
     *
     * @return array<string, mixed>|null Объект или null.
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /**
     * Архив.
     *
     * @return bool Флаг.
     */
    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    /**
     * Добавлен.
     *
     * @return DateTime Дата.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
