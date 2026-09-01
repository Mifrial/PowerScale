<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

/**
 * Порт записи ошибок ядра.
 */
interface ILogger
{
    /**
     * Пишет сообщение об ошибке.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void;
}
