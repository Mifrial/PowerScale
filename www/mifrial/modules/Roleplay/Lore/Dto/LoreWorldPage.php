<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Страница миров кодекса.
 */
final class LoreWorldPage
{
    /**
     * Создаёт страницу.
     *
     * @param array<int, LoreWorldRecord> $items Миры.
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
     * Миры страницы.
     *
     * @return array<int, LoreWorldRecord> Миры.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Всего миров.
     *
     * @return int Всего.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
