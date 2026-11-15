<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

/**
 * Мир с деревом разделов и срезами статей.
 */
final class LoreWorldDetail
{
    /**
     * Собирает снимок.
     *
     * @param LoreWorldRecord $worldRecord Мир.
     * @param array<int, LoreSectionRecord> $sections Разделы.
     * @param array<int, LoreArticleSummary> $articles Срезы статей.
     * @param bool $canEdit Правка доступна.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreWorldRecord $worldRecord,
        private readonly array $sections,
        private readonly array $articles,
        private readonly bool $canEdit,
    ) {
    }

    /**
     * Мир.
     *
     * @return LoreWorldRecord Запись.
     */
    public function getWorld(): LoreWorldRecord
    {
        return $this->worldRecord;
    }

    /**
     * Разделы.
     *
     * @return array<int, LoreSectionRecord> Разделы.
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Срезы статей.
     *
     * @return array<int, LoreArticleSummary> Статьи.
     */
    public function getArticles(): array
    {
        return $this->articles;
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
     * Срез статьи по коду.
     *
     * @param string $articleCode Код.
     *
     * @return LoreArticleSummary|null Статья или null.
     */
    public function findArticle(string $articleCode): ?LoreArticleSummary
    {
        foreach ($this->articles as $articleSummary) {
            if ($articleSummary->getCode() === $articleCode) {
                return $articleSummary;
            }
        }

        return null;
    }

    /**
     * Раздел по коду или отказ.
     *
     * @param string $sectionCode Код.
     *
     * @return LoreSectionRecord Раздел.
     *
     * @throws LoreInvalidException Если раздела нет.
     */
    public function requireSection(string $sectionCode): LoreSectionRecord
    {
        foreach ($this->sections as $sectionRecord) {
            if ($sectionRecord->getCode() === $sectionCode) {
                return $sectionRecord;
            }
        }

        throw new LoreInvalidException('Lore section ' . $sectionCode . ' was not found');
    }
}
