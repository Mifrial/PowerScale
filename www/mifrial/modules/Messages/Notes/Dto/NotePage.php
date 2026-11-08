<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto;

/**
 * Страница заметок и COUNT фильтра.
 */
final class NotePage
{
    /**
     * Собирает страницу.
     *
     * @param array<int, NoteRecord> $items Строки.
     * @param int $total Число совпадений.
     *
     * @return void
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
    ) {
    }

    /**
     * Строки страницы.
     *
     * @return array<int, NoteRecord> Заметки.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Полное число совпадений.
     *
     * @return int COUNT.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
