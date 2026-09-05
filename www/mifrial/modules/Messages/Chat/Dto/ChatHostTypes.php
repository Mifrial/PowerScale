<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

/**
 * Allowlist inbox/SSE: только private и group.
 */
final class ChatHostTypes
{
    /**
     * Host-чат мессенджера.
     *
     * @param string $type Значение type.
     *
     * @return bool true если private или group.
     */
    public static function isHost(string $type): bool
    {
        return $type === 'private' || $type === 'group';
    }

    /**
     * Host-id в порядке входного списка.
     *
     * @param array<int, int> $chatIds Кандидаты.
     * @param array<int, ChatRecord> $chats Строки.
     *
     * @return array<int, int> Host-id.
     */
    public static function hostIds(array $chatIds, array $chats): array
    {
        $hostById = [];
        foreach ($chats as $chat) {
            if (self::isHost($chat->getType())) {
                $hostById[$chat->getId()] = true;
            }
        }

        $hostIds = [];
        foreach ($chatIds as $chatId) {
            if (isset($hostById[$chatId])) {
                $hostIds[] = $chatId;
            }
        }

        return $hostIds;
    }

    /**
     * Только host-записи.
     *
     * @param array<int, ChatRecord> $chats Строки.
     *
     * @return array<int, ChatRecord> Host.
     */
    public static function hostChats(array $chats): array
    {
        $hostChats = [];
        foreach ($chats as $chat) {
            if (self::isHost($chat->getType())) {
                $hostChats[] = $chat;
            }
        }

        return $hostChats;
    }
}
