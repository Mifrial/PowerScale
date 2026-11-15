<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Проверенные поля статьи для записи.
 */
final class LoreArticleValues
{
    /**
     * Собирает поля.
     *
     * @param string $code Стабильный код.
     * @param string $title Заголовок.
     * @param string $summary Краткое описание.
     * @param string $bodyHtml HTML-тело.
     * @param string $bodyText Производный текст.
     * @param string|null $sectionCode Код раздела.
     * @param int $sortOrder Порядок.
     * @param array<int, int> $keywordIds Признаки.
     * @param string $status Draft или published.
     * @param string $visibility Public или secret.
     * @param array<int, int> $readerIds Читатели secret.
     *
     * @return void
     */
    public function __construct(
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
    ) {
    }

    /**
     * Код.
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
     * Читатели secret.
     *
     * @return array<int, int> Id учёток.
     */
    public function getReaderIds(): array
    {
        return $this->readerIds;
    }
}
