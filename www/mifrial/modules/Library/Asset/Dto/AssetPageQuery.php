<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto;

use Mifrial\Library\Asset\Enum\AssetScope;

/**
 * Проверенный запрос страницы ассетов.
 */
final class AssetPageQuery
{
    /**
     * Собирает запрос.
     *
     * @param int $collectionId Назначение.
     * @param AssetScope $scope Общая или личная часть.
     * @param int $viewerId Зритель для личной части.
     * @param string|null $searchQuery Подстрока имени.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param bool $hasMetaOnly Только записи с данными потребителя.
     *
     * @return void
     */
    public function __construct(
        private readonly int $collectionId,
        private readonly AssetScope $scope,
        private readonly int $viewerId,
        private readonly ?string $searchQuery,
        private readonly int $limit,
        private readonly int $offset,
        private readonly bool $hasMetaOnly = false,
    ) {
    }

    /**
     * Назначение.
     *
     * @return int Id коллекции.
     */
    public function getCollectionId(): int
    {
        return $this->collectionId;
    }

    /**
     * Часть библиотеки.
     *
     * @return AssetScope Часть.
     */
    public function getScope(): AssetScope
    {
        return $this->scope;
    }

    /**
     * Зритель.
     *
     * @return int Id учётки.
     */
    public function getViewerId(): int
    {
        return $this->viewerId;
    }

    /**
     * Подстрока имени.
     *
     * @return string|null Подстрока или null.
     */
    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
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
     * Только записи с данными потребителя.
     *
     * @return bool Флаг.
     */
    public function hasMetaOnly(): bool
    {
        return $this->hasMetaOnly;
    }
}
