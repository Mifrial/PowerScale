<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Interface\Service;

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Часы и пауза цикла SSE; в тестах подмена без sleep(2).
 */
interface ISseClock
{
    /**
     * Горизонт тика.
     *
     * @return DateTime Сейчас по часам цикла.
     */
    public function now(): DateTime;

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
