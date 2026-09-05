<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

/**
 * Страница сообщений и COUNT чата.
 */
final class MessagePage
{
    /**
     * Собирает страницу.
     *
     * @param array<int, MessageRecord> $items Строки.
     * @param int $total Число сообщений чата.
     *
     * @return void
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
    ) {
    }

    /**
     * Сообщения страницы.
     *
     * @return array<int, MessageRecord> Сообщения.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Полное число сообщений чата.
     *
     * @return int COUNT.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
