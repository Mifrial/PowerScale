<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `map`.
 */
final class MapRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $ownerId Владелец.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param string $visibility public|private.
     * @param bool $isArchived Архив.
     * @param int $version Версия публикации.
     * @param int|null $updatedBy Кто опубликовал.
     * @param DateTime $createdAt Создана.
     * @param DateTime $updatedAt Обновлена.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $ownerId,
        private readonly string $name,
        private readonly string $description,
        private readonly string $visibility,
        private readonly bool $isArchived,
        private readonly int $version,
        private readonly ?int $updatedBy,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            MapValues::requireInt($fields, 'id'),
            MapValues::requireInt($fields, 'owner_id'),
            MapValues::requireString($fields, 'name'),
            MapValues::requireString($fields, 'description'),
            MapValues::requireString($fields, 'visibility'),
            MapValues::requireBool($fields, 'is_archived'),
            MapValues::requireInt($fields, 'version'),
            MapValues::optionalInt($fields, 'updated_by'),
            MapValues::requireDateTime($fields, 'created_at'),
            MapValues::requireDateTime($fields, 'updated_at'),
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
     * Владелец.
     *
     * @return int Id учётки.
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Видимость.
     *
     * @return string public|private.
     */
    public function getVisibility(): string
    {
        return $this->visibility;
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
     * Версия.
     *
     * @return int Версия.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Кто опубликовал.
     *
     * @return int|null Id учётки или null.
     */
    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    /**
     * Создана.
     *
     * @return DateTime Дата.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Обновлена.
     *
     * @return DateTime Дата.
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
