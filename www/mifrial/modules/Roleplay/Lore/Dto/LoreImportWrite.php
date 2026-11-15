<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Одна запись статьи при импорте.
 */
final class LoreImportWrite
{
    /**
     * Собирает запись.
     *
     * @param LoreArticleValues $values Поля.
     * @param LoreArticleRecord|null $existing Текущая статья.
     * @param bool $isArchived Архив.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreArticleValues $values,
        private readonly ?LoreArticleRecord $existing,
        private readonly bool $isArchived,
    ) {
    }

    /**
     * Поля.
     *
     * @return LoreArticleValues Поля.
     */
    public function getValues(): LoreArticleValues
    {
        return $this->values;
    }

    /**
     * Текущая статья.
     *
     * @return LoreArticleRecord|null Статья или null.
     */
    public function getExisting(): ?LoreArticleRecord
    {
        return $this->existing;
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
