<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Interface\Service\IGameSyncClock;

/**
 * Пауза без sleep ОС. remainingLoops — сколько раз войти в цикл после hello.
 */
final class FakeGameSyncClock implements IGameSyncClock
{
    /**
     * Задаёт число тиков цикла.
     *
     * @param int $remainingLoops Входы в цикл.
     *
     * @return void
     */
    public function __construct(private int $remainingLoops)
    {
    }

    /**
     * Не ждёт.
     *
     * @param int $seconds Секунды.
     *
     * @return void
     */
    public function sleep(int $seconds): void
    {
        unset($seconds);
        $this->remainingLoops--;
    }

    /**
     * Цикл кончился.
     *
     * @return bool true, если входить больше не надо.
     */
    public function isAborted(): bool
    {
        return $this->remainingLoops <= 0;
    }
}
