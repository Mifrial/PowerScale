<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Проверенный документ импорта до записи.
 */
final class LoreImportDocument
{
    /**
     * Собирает документ.
     *
     * @param LoreWorldValues $worldValues Мета мира.
     * @param array<int, LoreSectionValues> $sections Разделы.
     * @param array<int, LoreKeywordSnapshot> $keywords Признаки.
     * @param array<int, LoreImportArticle> $articles Статьи.
     * @param array<int, array{code: string, path: string, message: string}> $warnings Предупреждения.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreWorldValues $worldValues,
        private readonly array $sections,
        private readonly array $keywords,
        private readonly array $articles,
        private readonly array $warnings,
    ) {
    }

    /**
     * Мета мира.
     *
     * @return LoreWorldValues Поля.
     */
    public function getWorldValues(): LoreWorldValues
    {
        return $this->worldValues;
    }

    /**
     * Разделы.
     *
     * @return array<int, LoreSectionValues> Разделы.
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Признаки.
     *
     * @return array<int, LoreKeywordSnapshot> Снимки.
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * Статьи.
     *
     * @return array<int, LoreImportArticle> Статьи.
     */
    public function getArticles(): array
    {
        return $this->articles;
    }

    /**
     * Предупреждения разбора.
     *
     * @return array<int, array{code: string, path: string, message: string}> Предупреждения.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
