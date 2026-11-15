<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Статья с исходящими ссылками, ссылками сюда и читателями.
 */
final class LoreArticleDetail
{
    /**
     * Собирает снимок.
     *
     * @param LoreArticleRecord $articleRecord Статья.
     * @param bool $canEdit Правка доступна.
     * @param array<int, LoreArticleLink> $links Исходящие ссылки.
     * @param array<int, LoreBacklink> $backlinks Ссылки сюда.
     * @param array<int, LoreReader> $readers Читатели secret.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreArticleRecord $articleRecord,
        private readonly bool $canEdit,
        private readonly array $links,
        private readonly array $backlinks,
        private readonly array $readers,
    ) {
    }

    /**
     * Статья.
     *
     * @return LoreArticleRecord Запись.
     */
    public function getArticle(): LoreArticleRecord
    {
        return $this->articleRecord;
    }

    /**
     * Правка доступна.
     *
     * @return bool Флаг.
     */
    public function canEdit(): bool
    {
        return $this->canEdit;
    }

    /**
     * Исходящие ссылки.
     *
     * @return array<int, LoreArticleLink> Ссылки.
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Ссылки сюда.
     *
     * @return array<int, LoreBacklink> Ссылки.
     */
    public function getBacklinks(): array
    {
        return $this->backlinks;
    }

    /**
     * Читатели.
     *
     * @return array<int, LoreReader> Читатели.
     */
    public function getReaders(): array
    {
        return $this->readers;
    }
}
