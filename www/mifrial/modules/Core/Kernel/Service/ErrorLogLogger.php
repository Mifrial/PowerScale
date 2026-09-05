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
     * Пишет ошибку в error_log.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * Пишет предупреждение в error_log.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /**
     * Пишет информацию в error_log.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /**
     * Пишет отладку в error_log.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    /**
     * Пишет строку в error_log.
     *
     * @param string $level Уровень.
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    private function write(string $level, string $message, array $context): void
    {
        $details = [];
        foreach ($context as $contextKey => $contextValue) {
            if (is_scalar($contextValue) || $contextValue === null) {
                $details[] = $contextKey . '=' . (string) $contextValue;
            }
        }

        $suffix = $details === [] ? '' : ' ' . implode(' ', $details);
        error_log('[' . $level . '] ' . $message . $suffix);
    }
}
