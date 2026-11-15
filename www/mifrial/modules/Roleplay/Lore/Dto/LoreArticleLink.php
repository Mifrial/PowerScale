<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Исходящая ссылка статьи.
 */
final class LoreArticleLink
{
    /**
     * Собирает ссылку.
     *
     * @param string $type Lore, rule или тип inline-сущности.
     * @param string $code Код цели.
     *
     * @return void
     */
    public function __construct(
        private readonly string $type,
        private readonly string $code,
    ) {
    }

    /**
     * Тип цели.
     *
     * @return string Тип.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Код цели.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }
}
