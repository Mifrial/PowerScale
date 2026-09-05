<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Chat\Interface\Service\ISseClock;

/**
 * Часы цикла без sleep ОС: N итераций, unix сдвигается на аргумент sleep.
 */
final class FakeSseClock implements ISseClock
{
    /**
     * Задаёт горизонт и число тиков после open.
     *
     * @param int $unix Начальные unix-секунды.
     * @param int $remainingLoops Сколько раз войти в цикл.
     *
     * @return void
     */
    public function __construct(
        private int $unix,
        private int $remainingLoops,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function now(): DateTime
    {
        return DateTime::fromUnix($this->unix);
    }

    /**
     * {@inheritdoc}
     */
    public function sleep(int $seconds): void
    {
        $this->unix += $seconds;
        $this->remainingLoops--;
    }

    /**
     * {@inheritdoc}
     */
    public function isAborted(): bool
    {
        return $this->remainingLoops <= 0;
    }
}
