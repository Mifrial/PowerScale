<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Проверенный запрос страницы карт.
 */
final class MapPageQuery
{
    /**
     * Собирает запрос.
     *
     * @param int $viewerId Зритель.
     * @param bool $canViewAll Видит все карты.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока имени.
     * @param int|null $ownerId Фильтр владельца.
     * @param bool $isArchived Архивные.
     *
     * @return void
     */
    public function __construct(
        private readonly int $viewerId,
        private readonly bool $canViewAll,
        private readonly int $limit,
        private readonly int $offset,
        private readonly ?string $searchQuery,
        private readonly ?int $ownerId,
        private readonly bool $isArchived,
    ) {
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
     * Владелец.
     *
     * @return int|null Id или null.
     */
    public function getOwnerId(): ?int
    {
        return $this->ownerId;
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
}
