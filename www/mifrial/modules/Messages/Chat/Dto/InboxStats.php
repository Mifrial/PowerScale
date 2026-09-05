<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

/**
 * Unread и id последнего видимого сообщения по чатам inbox.
 */
final class InboxStats
{
    /**
     * Создаёт пачку счётчиков.
     *
     * @param array<int, int> $unreadByChatId Chat id → число непрочитанных.
     * @param array<int, int> $lastMessageIdByChatId Chat id → max видимого id.
     *
     * @return void
     */
    public function __construct(
        private readonly array $unreadByChatId,
        private readonly array $lastMessageIdByChatId,
    ) {
    }

    /**
     * Unread чата или 0 при промахе группы.
     *
     * @param int $chatId Чат.
     *
     * @return int Число.
     */
    public function unreadCountOf(int $chatId): int
    {
        return $this->unreadByChatId[$chatId] ?? 0;
    }

    /**
     * Id последнего видимого или null при промахе.
     *
     * @param int $chatId Чат.
     *
     * @return int|null Id.
     */
    public function lastMessageIdOf(int $chatId): ?int
    {
        return $this->lastMessageIdByChatId[$chatId] ?? null;
    }

    /**
     * Id preview-строк без дублей.
     *
     * @return array<int, int> Id сообщений.
     */
    public function lastMessageIds(): array
    {
        return array_values(array_unique($this->lastMessageIdByChatId));
    }
}
