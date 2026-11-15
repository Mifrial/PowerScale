<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Страница статей кодекса.
 */
final class LoreArticlePage
{
    /**
     * Создаёт страницу.
     *
     * @param array<int, LoreArticleSummary> $items Статьи.
     * @param int $total Всего.
     *
     * @return void
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
    ) {
    }

    /**
     * Статьи страницы.
     *
     * @return array<int, LoreArticleSummary> Статьи.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Всего статей.
     *
     * @return int Всего.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
