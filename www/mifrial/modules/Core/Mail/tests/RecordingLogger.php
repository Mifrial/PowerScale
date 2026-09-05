<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Tests;

use Mifrial\Core\Kernel\Interface\Service\ILogger;

/**
 * Логер для проверки flush.
 */
final class RecordingLogger implements ILogger
{
    /**
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $entries = [];

    /**
     * Пишет ошибку.
     *
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    /**
     * Пишет предупреждение.
     *
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    /**
     * Пишет информацию.
     *
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    /**
     * Пишет отладку.
     *
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }
}
