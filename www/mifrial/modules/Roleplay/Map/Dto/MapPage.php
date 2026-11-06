<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

/**
 * Страница карт.
 */
final class MapPage
{
    /**
     * Создаёт страницу.
     *
     * @param array<int, MapRecord> $items Карты.
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
     * Карты страницы.
     *
     * @return array<int, MapRecord> Карты.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Всего карт.
     *
     * @return int Всего.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
