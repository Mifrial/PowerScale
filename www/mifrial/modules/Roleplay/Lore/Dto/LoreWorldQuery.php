<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Проверенный запрос страницы миров.
 */
final class LoreWorldQuery
{
    /**
     * Собирает запрос.
     *
     * @param int $viewerId Зритель.
     * @param bool $canViewAll Видит все миры.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока имени или описания.
     * @param string $state Active, archived или all.
     * @param string|null $visibility Public или private.
     *
     * @return void
     */
    public function __construct(
        private readonly int $viewerId,
        private readonly bool $canViewAll,
        private readonly int $limit,
        private readonly int $offset,
        private readonly ?string $searchQuery,
        private readonly string $state,
        private readonly ?string $visibility,
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
     * Состояние архива.
     *
     * @return string Active, archived или all.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Фильтр видимости.
     *
     * @return string|null Public, private или null.
     */
    public function getVisibility(): ?string
    {
        return $this->visibility;
    }
}
