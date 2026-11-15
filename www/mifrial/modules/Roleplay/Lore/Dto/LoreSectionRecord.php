<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `lore_section`.
 */
final class LoreSectionRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $worldId Мир.
     * @param string $code Код узла.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param string|null $parentCode Код родителя.
     * @param int $sortOrder Порядок.
     * @param string $icon mdi-иконка.
     * @param DateTime $createdAt Создан.
     * @param DateTime $updatedAt Обновлён.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $worldId,
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly ?string $parentCode,
        private readonly int $sortOrder,
        private readonly string $icon,
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
            LoreValues::requireInt($fields, 'world_id'),
            LoreValues::requireString($fields, 'code'),
            LoreValues::requireString($fields, 'name'),
            LoreValues::requireString($fields, 'description'),
            LoreValues::optionalString($fields, 'parent_code'),
            LoreValues::requireInt($fields, 'sort_order'),
            LoreValues::requireString($fields, 'icon'),
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
     * Мир.
     *
     * @return int Id мира.
     */
    public function getWorldId(): int
    {
        return $this->worldId;
    }

    /**
     * Код узла.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
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
     * Код родителя.
     *
     * @return string|null Код или null.
     */
    public function getParentCode(): ?string
    {
        return $this->parentCode;
    }

    /**
     * Порядок.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * mdi-иконка.
     *
     * @return string Иконка или пустая строка.
     */
    public function getIcon(): string
    {
        return $this->icon;
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
