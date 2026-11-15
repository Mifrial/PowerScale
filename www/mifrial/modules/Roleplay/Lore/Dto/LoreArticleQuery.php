<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Проверенный запрос статей мира.
 */
final class LoreArticleQuery
{
    /**
     * Собирает запрос.
     *
     * @param int $worldId Мир.
     * @param int $viewerId Зритель.
     * @param bool $canViewAll Видит черновики и секреты.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока заголовка, описания или тела.
     * @param string|null $sectionCode Раздел.
     * @param array<int, int> $keywordIds Признаки.
     * @param string|null $status Draft или published.
     * @param string|null $visibility Public или secret.
     * @param string $sort Order или recent.
     * @param bool $includeArchived Показывать архивные.
     *
     * @return void
     */
    public function __construct(
        private readonly int $worldId,
        private readonly int $viewerId,
        private readonly bool $canViewAll,
        private readonly int $limit,
        private readonly int $offset,
        private readonly ?string $searchQuery,
        private readonly ?string $sectionCode,
        private readonly array $keywordIds,
        private readonly ?string $status,
        private readonly ?string $visibility,
        private readonly string $sort,
        private readonly bool $includeArchived,
    ) {
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
     * Зритель.
     *
     * @return int Id.
     */
    public function getViewerId(): int
    {
        return $this->viewerId;
    }

    /**
     * Видит все.
     *
     * @return bool Флаг.
     */
    public function canViewAll(): bool
    {
        return $this->canViewAll;
    }

    /**
     * Размер страницы.
     *
     * @return int Limit.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Сдвиг.
     *
     * @return int Offset.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Подстрока.
     *
     * @return string|null Подстрока или null.
     */
    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    /**
     * Раздел.
     *
     * @return string|null Код или null.
     */
    public function getSectionCode(): ?string
    {
        return $this->sectionCode;
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
     * @return string|null Draft, published или null.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Видимость.
     *
     * @return string|null Public, secret или null.
     */
    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    /**
     * Сортировка.
     *
     * @return string Order или recent.
     */
    public function getSort(): string
    {
        return $this->sort;
    }

    /**
     * Показывать архивные.
     *
     * @return bool Флаг.
     */
    public function includeArchived(): bool
    {
        return $this->includeArchived;
    }
}
