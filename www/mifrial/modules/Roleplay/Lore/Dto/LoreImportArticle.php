<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Проверенная статья файла импорта: коды признаков и логины читателей.
 */
final class LoreImportArticle
{
    /**
     * Собирает статью файла.
     *
     * @param LoreArticleValues $values Поля статьи.
     * @param array<int, string> $keywordCodes Коды признаков.
     * @param array<int, string> $readerLogins Логины читателей.
     * @param bool $isArchived Архив.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreArticleValues $values,
        private readonly array $keywordCodes,
        private readonly array $readerLogins,
        private readonly bool $isArchived,
    ) {
    }

    /**
     * Поля статьи.
     *
     * @return LoreArticleValues Поля.
     */
    public function getValues(): LoreArticleValues
    {
        return $this->values;
    }

    /**
     * Коды признаков.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywordCodes(): array
    {
        return $this->keywordCodes;
    }

    /**
     * Логины читателей.
     *
     * @return array<int, string> Логины.
     */
    public function getReaderLogins(): array
    {
        return $this->readerLogins;
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
