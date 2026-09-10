<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Exception;

use Throwable;

/**
 * Оптимистичный lock: expectedVersion не совпал с actual_version.
 */
final class CharacterConflictException extends CharacterException
{
    /**
     * Создаёт конфликт версии строки.
     *
     * @param int $currentVersion Актуальный actual_version.
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly int $currentVersion,
        string $message = 'Character version conflict',
        ?Throwable $previous = null,
    ) {
        parent::__construct('CHARACTER_CONFLICT', $message, $previous);
    }

    /**
     * Текущая версия строки в БД.
     *
     * @return int actual_version.
     */
    public function getCurrentVersion(): int
    {
        return $this->currentVersion;
    }
}
