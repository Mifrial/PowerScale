<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

/**
 * Пауза и обрыв потока доставки. Не часы чата.
 */
interface IGameSyncClock
{
    /**
     * Пауза до следующего тика.
     *
     * @param int $seconds Секунды.
     *
     * @return void
     */
    public function sleep(int $seconds): void;

    /**
     * Клиент закрыл соединение.
     *
     * @return bool true, если цикл пора остановить.
     */
    public function isAborted(): bool;
}
