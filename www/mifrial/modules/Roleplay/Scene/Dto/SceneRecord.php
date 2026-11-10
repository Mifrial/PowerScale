<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `scene_location`.
 */
final class SceneRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $ownerId Владелец.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param int $resolution Единиц сцены на ипари.
     * @param int $schemaVersion Версия формы снимка.
     * @param bool $isArchived Архив.
     * @param int $version Версия сохранения.
     * @param int|null $updatedBy Кто сохранил.
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
        private readonly int $resolution,
        private readonly int $schemaVersion,
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
            SceneValues::requireInt($fields, 'id'),
            SceneValues::requireInt($fields, 'owner_id'),
            SceneValues::requireString($fields, 'name'),
            SceneValues::requireString($fields, 'description'),
            SceneValues::requireInt($fields, 'resolution'),
            SceneValues::requireInt($fields, 'schema_version'),
            SceneValues::requireBool($fields, 'is_archived'),
            SceneValues::requireInt($fields, 'version'),
            SceneValues::optionalInt($fields, 'updated_by'),
            SceneValues::requireDateTime($fields, 'created_at'),
            SceneValues::requireDateTime($fields, 'updated_at'),
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
     * Разрешение.
     *
     * @return int Единиц на ипари.
     */
    public function getResolution(): int
    {
        return $this->resolution;
    }

    /**
     * Версия формы снимка.
     *
     * @return int Версия.
     */
    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
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
     * Версия сохранения.
     *
     * @return int Версия.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Кто сохранил.
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
