<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

/**
 * Порт записи журнала ядра.
 */
interface ILogger
{
    /**
     * Пишет ошибку.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Пишет предупреждение.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Пишет информационную запись.
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Пишет отладочную запись (не в таблицу).
     *
     * @param string $message Текст сообщения.
     * @param array<string, mixed> $context Дополнительный контекст.
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void;
}
