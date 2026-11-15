<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Статья без тела: узел дерева и строка списка.
 */
final class LoreArticleSummary
{
    /**
     * Собирает срез.
     *
     * @param int $id Id.
     * @param int $worldId Мир.
     * @param string $code Код.
     * @param string $title Заголовок.
     * @param string $summary Краткое описание.
     * @param string|null $sectionCode Код раздела.
     * @param int $sortOrder Порядок.
     * @param array<int, int> $keywordIds Признаки.
     * @param string $status draft или published.
     * @param string $visibility public или secret.
     * @param bool $isArchived Архив.
     * @param int $version Версия.
     * @param DateTime $updatedAt Обновлена.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $worldId,
        private readonly string $code,
        private readonly string $title,
        private readonly string $summary,
        private readonly ?string $sectionCode,
        private readonly int $sortOrder,
        private readonly array $keywordIds,
        private readonly string $status,
        private readonly string $visibility,
        private readonly bool $isArchived,
        private readonly int $version,
        private readonly DateTime $updatedAt,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Срез.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            LoreValues::requireInt($fields, 'id'),
            LoreValues::requireInt($fields, 'world_id'),
            LoreValues::requireString($fields, 'code'),
            LoreValues::requireString($fields, 'title'),
            LoreValues::requireString($fields, 'summary'),
            LoreValues::optionalString($fields, 'section_code'),
            LoreValues::requireInt($fields, 'sort_order'),
            LoreValues::requireIntList($fields, 'keywords'),
            LoreValues::requireString($fields, 'status'),
            LoreValues::requireString($fields, 'visibility'),
            LoreValues::requireBool($fields, 'is_archived'),
            LoreValues::requireInt($fields, 'version'),
            LoreValues::requireDateTime($fields, 'updated_at'),
        );
    }

    /**
     * Срез из полной записи.
     *
     * @param LoreArticleRecord $articleRecord Статья.
     *
     * @return self Срез.
     */
    public static function fromRecord(LoreArticleRecord $articleRecord): self
    {
        return new self(
            $articleRecord->getId(),
            $articleRecord->getWorldId(),
            $articleRecord->getCode(),
            $articleRecord->getTitle(),
            $articleRecord->getSummary(),
            $articleRecord->getSectionCode(),
            $articleRecord->getSortOrder(),
            $articleRecord->getKeywordIds(),
            $articleRecord->getStatus(),
            $articleRecord->getVisibility(),
            $articleRecord->isArchived(),
            $articleRecord->getVersion(),
            $articleRecord->getUpdatedAt(),
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
     * Код статьи.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Заголовок.
     *
     * @return string Заголовок.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Краткое описание.
     *
     * @return string Текст.
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * Код раздела.
     *
     * @return string|null Код или null.
     */
    public function getSectionCode(): ?string
    {
        return $this->sectionCode;
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
     * Признаки.
     *
     * @return array<int, int> Id признаков.
     */
    public function getKeywordIds(): array
    {
        return $this->keywordIds;
    }

    /**
     * Статус.
     *
     * @return string draft или published.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Видимость.
     *
     * @return string public или secret.
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
     * Обновлена.
     *
     * @return DateTime Дата.
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
