<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Interface\Service;

/**
 * Сосед: поставить событие в очередь.
 */
interface IMail
{
    /**
     * Пишет job по коду события.
     *
     * @param string $eventCode Код `mail_event`.
     * @param array<string, mixed> $payload Поля подстановки.
     *
     * @return void
     */
    public function trigger(string $eventCode, array $payload): void;
}
