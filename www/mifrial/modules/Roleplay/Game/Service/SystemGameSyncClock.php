<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Interface\Service\IGameSyncClock;

/**
 * sleep ОС и connection_aborted.
 */
final class SystemGameSyncClock implements IGameSyncClock
{
    /**
     * Пауза до следующего тика.
     *
     * @param int $seconds Секунды.
     *
     * @return void
     */
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * Клиент закрыл соединение.
     *
     * @return bool true, если цикл пора остановить.
     */
    public function isAborted(): bool
    {
        return connection_aborted() === 1;
    }
}
