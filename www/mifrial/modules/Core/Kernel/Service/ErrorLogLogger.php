<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Interface\Service\ILogger;

/**
 * Адаптер ILogger через error_log.
 */
final class ErrorLogLogger implements ILogger
{
    /**
     * Пишет сообщение в error_log PHP.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $details = [];
        foreach ($context as $contextKey => $contextValue) {
            if (is_scalar($contextValue) || $contextValue === null) {
                $details[] = $contextKey . '=' . (string) $contextValue;
            }
        }

        $suffix = $details === [] ? '' : ' ' . implode(' ', $details);
        error_log($message . $suffix);
    }
}
