<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

/**
 * Персонаж уже в текущей сессии какой-либо игры. Реализацию даёт Game.
 */
interface ICharacterSessionParticipants
{
    /**
     * Состав текущей сессии содержит этот персонаж.
     *
     * @param int $characterId Персонаж.
     *
     * @return bool true, если migrate этой сессии запрещён.
     */
    public function isActiveSessionParticipant(int $characterId): bool;
}
