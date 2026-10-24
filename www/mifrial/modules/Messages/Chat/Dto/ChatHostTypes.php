<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

/**
 * Зарезервированные типы мессенджера.
 */
final class ChatHostTypes
{
    /**
     * Зарезервированный тип.
     *
     * @param string $type Значение type.
     *
     * @return bool true если private или group.
     */
    public static function isHost(string $type): bool
    {
        return $type === 'private' || $type === 'group';
    }
}
