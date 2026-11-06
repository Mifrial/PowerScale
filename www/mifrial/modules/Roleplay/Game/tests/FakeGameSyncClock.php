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
     * Сколько тиков цикл уже сделал.
     */
    public int $ticks = 0;

    /**
     * Задаёт число тиков и стартовые секунды.
     *
     * @param int $remainingLoops Входы в цикл.
     * @param int $unix Стартовые unix-секунды.
     *
     * @return void
     */
    public function __construct(
        private int $remainingLoops,
        private int $unix = 1_700_000_000,
    ) {
    }

    /**
     * Секунды без sleep ОС.
     *
     * @return int Секунды.
     */
    public function now(): int
    {
        return $this->unix;
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
        $this->unix += $seconds;
        $this->ticks++;
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
