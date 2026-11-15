<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Статья, которая ссылается на текущую.
 */
final class LoreBacklink
{
    /**
     * Собирает ссылку сюда.
     *
     * @param int $articleId Id источника.
     * @param string $code Код источника.
     * @param string $title Заголовок источника.
     *
     * @return void
     */
    public function __construct(
        private readonly int $articleId,
        private readonly string $code,
        private readonly string $title,
    ) {
    }

    /**
     * Id источника.
     *
     * @return int Id.
     */
    public function getArticleId(): int
    {
        return $this->articleId;
    }

    /**
     * Код источника.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Заголовок источника.
     *
     * @return string Заголовок.
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}
