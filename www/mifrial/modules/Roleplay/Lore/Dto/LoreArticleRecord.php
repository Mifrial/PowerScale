<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `lore_article` с телом.
 */
final class LoreArticleRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $worldId Мир.
     * @param string $code Стабильный код.
     * @param string $title Заголовок.
     * @param string $summary Краткое описание.
     * @param string $bodyHtml HTML-тело.
     * @param string $bodyText Производный текст.
     * @param string|null $sectionCode Код раздела.
     * @param int $sortOrder Порядок.
     * @param array<int, int> $keywordIds Признаки.
     * @param string $status draft или published.
     * @param string $visibility public или secret.
     * @param array<int, int> $readerIds Читатели secret.
     * @param int $version Версия статьи.
     * @param bool $isArchived Архив.
     * @param int $createdBy Автор.
     * @param int|null $updatedBy Кто сохранил.
     * @param DateTime $createdAt Создана.
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
        private readonly string $bodyHtml,
        private readonly string $bodyText,
        private readonly ?string $sectionCode,
        private readonly int $sortOrder,
        private readonly array $keywordIds,
        private readonly string $status,
        private readonly string $visibility,
        private readonly array $readerIds,
        private readonly int $version,
        private readonly bool $isArchived,
        private readonly int $createdBy,
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
            LoreValues::requireInt($fields, 'world_id'),
            LoreValues::requireString($fields, 'code'),
            LoreValues::requireString($fields, 'title'),
            LoreValues::requireString($fields, 'summary'),
            LoreValues::requireString($fields, 'body_html'),
            LoreValues::requireString($fields, 'body_text'),
            LoreValues::optionalString($fields, 'section_code'),
            LoreValues::requireInt($fields, 'sort_order'),
            LoreValues::requireIntList($fields, 'keywords'),
            LoreValues::requireString($fields, 'status'),
            LoreValues::requireString($fields, 'visibility'),
            LoreValues::requireIntList($fields, 'readers'),
            LoreValues::requireInt($fields, 'version'),
            LoreValues::requireBool($fields, 'is_archived'),
            LoreValues::requireInt($fields, 'created_by'),
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
     * HTML-тело.
     *
     * @return string HTML.
     */
    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    /**
     * Производный текст.
     *
     * @return string Текст.
     */
    public function getBodyText(): string
    {
        return $this->bodyText;
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
     * Порядок в разделе.
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
     * Читатели secret.
     *
     * @return array<int, int> Id учёток.
     */
    public function getReaderIds(): array
    {
        return $this->readerIds;
    }

    /**
     * Версия статьи.
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
     * Автор.
     *
     * @return int Id учётки.
     */
    public function getCreatedBy(): int
    {
        return $this->createdBy;
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
