<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto;

/**
 * Страница ассетов одной коллекции и части библиотеки.
 */
final class AssetPage
{
    /**
     * Собирает страницу.
     *
     * @param array<int, AssetRecord> $items Ассеты.
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
     * Ассеты страницы.
     *
     * @return array<int, AssetRecord> Ассеты.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Всего ассетов.
     *
     * @return int Всего.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
