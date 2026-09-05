<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Chat\Interface\Service\ISseClock;

/**
 * Часы процесса и sleep ОС; abort — connection_aborted.
 */
final class SystemSseClock implements ISseClock
{
    /**
     * Горизонт тика.
     *
     * @return DateTime Сейчас по часам процесса.
     */
    public function now(): DateTime
    {
        return DateTime::now();
    }

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
