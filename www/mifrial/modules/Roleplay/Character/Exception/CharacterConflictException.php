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
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>}|null $currentSheet
     *     Внутренний снимок или уже разрешённая проекция.
     *
     * @return void
     */
    public function __construct(
        private readonly int $currentVersion,
        string $message = 'Character version conflict',
        ?Throwable $previous = null,
        private readonly ?array $currentSheet = null,
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

    /**
     * Текущая версия в конверте ошибки.
     *
     * @return array<string, int> Поле currentVersion.
     */
    public function getErrorDetails(): array
    {
        $details = ['currentVersion' => $this->currentVersion];
        if ($this->currentSheet !== null) {
            $details['choices'] = $this->currentSheet['choices'];
            $details['sheet'] = $this->currentSheet['sheet'];
        }

        return $details;
    }

    /**
     * Свежий лист, если конфликт относится к строке персонажа.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>}|null Лист.
     */
    public function getCurrentSheet(): ?array
    {
        return $this->currentSheet;
    }
}
