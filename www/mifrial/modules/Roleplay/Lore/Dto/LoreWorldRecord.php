<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `lore_world`.
 */
final class LoreWorldRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $ownerId Владелец.
     * @param string $code Код URL.
     * @param string $name Название.
     * @param string $description Описание.
     * @param string $visibility public или private.
     * @param string $spaceCode Мягкая ссылка на пространство правил.
     * @param int $version Версия мета и дерева.
     * @param bool $isArchived Архив.
     * @param int|null $updatedBy Кто сохранил.
     * @param DateTime $createdAt Создан.
     * @param DateTime $updatedAt Обновлён.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $ownerId,
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly string $visibility,
        private readonly string $spaceCode,
        private readonly int $version,
        private readonly bool $isArchived,
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
            LoreValues::requireInt($fields, 'id'),
            LoreValues::requireInt($fields, 'owner_id'),
            LoreValues::requireString($fields, 'code'),
            LoreValues::requireString($fields, 'name'),
            LoreValues::requireString($fields, 'description'),
            LoreValues::requireString($fields, 'visibility'),
            LoreValues::requireString($fields, 'space_code'),
            LoreValues::requireInt($fields, 'version'),
            LoreValues::requireBool($fields, 'is_archived'),
            LoreValues::optionalInt($fields, 'updated_by'),
            LoreValues::requireDateTime($fields, 'created_at'),
            LoreValues::requireDateTime($fields, 'updated_at'),
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
     * Код URL.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Название.
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
     * Видимость мира.
     *
     * @return string public или private.
     */
    public function getVisibility(): string
    {
        return $this->visibility;
    }

    /**
     * Мягкая ссылка на пространство правил.
     *
     * @return string Код или пустая строка.
     */
    public function getSpaceCode(): string
    {
        return $this->spaceCode;
    }

    /**
     * Версия мета и дерева.
     *
     * @return int Версия.
     */
    public function getVersion(): int
    {
        return $this->version;
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
     * Кто сохранил.
     *
     * @return int|null Id учётки или null.
     */
    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    /**
     * Создан.
     *
     * @return DateTime Дата.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Обновлён.
     *
     * @return DateTime Дата.
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
