<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Closure;
use Mifrial\Core\Kernel\Interface\Service\ILogger;
use Throwable;

/**
 * Один логер процесса: fallback и ленивая подмена табличным адаптером.
 */
final class ProcessLogger implements ILogger
{
    private ILogger $inner;

    private bool $tableResolved = false;

    /**
     * Создаёт обёртку.
     *
     * @param ILogger $fallback Запасной адаптер (error_log).
     * @param Closure $tableLoggerFactory Сборка табличного ILogger; без аргументов.
     *
     * @return void
     */
    public function __construct(
        private readonly ILogger $fallback,
        private readonly Closure $tableLoggerFactory,
    ) {
        $this->inner = $fallback;
    }

    /**
     * Пишет ошибку.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->writeToTable('error', $message, $context);
    }

    /**
     * Пишет предупреждение.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->writeToTable('warning', $message, $context);
    }

    /**
     * Пишет информационную запись.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->writeToTable('info', $message, $context);
    }

    /**
     * Пишет отладку только в fallback.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->fallback->debug($message, $context);
    }

    /**
     * Пишет уровень, попадающий в таблицу.
     *
     * @param string $level error|warning|info.
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    private function writeToTable(string $level, string $message, array $context): void
    {
        $this->resolveTableLogger();
        try {
            match ($level) {
                'warning' => $this->inner->warning($message, $context),
                'info' => $this->inner->info($message, $context),
                default => $this->inner->error($message, $context),
            };
        } catch (Throwable) {
            $this->writeFallback($level, $message, $context);
        }
    }

    /**
     * Один раз пытается взять табличный адаптер.
     *
     * @return void
     */
    private function resolveTableLogger(): void
    {
        if ($this->tableResolved) {
            return;
        }

        $this->tableResolved = true;
        try {
            $tableLogger = ($this->tableLoggerFactory)();
            if ($tableLogger instanceof ILogger) {
                $this->inner = $tableLogger;
            }
        } catch (Throwable) {
            $this->inner = $this->fallback;
        }
    }

    /**
     * Запасной канал.
     *
     * @param string $level Уровень.
     * @param string $message Текст.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    private function writeFallback(string $level, string $message, array $context): void
    {
        match ($level) {
            'warning' => $this->fallback->warning($message, $context),
            'info' => $this->fallback->info($message, $context),
            default => $this->fallback->error($message, $context),
        };
    }
}
