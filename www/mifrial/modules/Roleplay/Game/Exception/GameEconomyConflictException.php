<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Exception;

use Throwable;

/**
 * Конфликт версии магазина, NPC или чужого тела ключа. Не CAS строки персонажа.
 */
final class GameEconomyConflictException extends GameException
{
    /**
     * Создаёт конфликт.
     *
     * @param int|null $currentVersion Текущая версия строки или null для ключа.
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>}|null $currentSheet
     *     Разрешённая проекция листа.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $currentVersion,
        string $message = 'Game economy version conflict',
        ?Throwable $previous = null,
        private readonly ?array $currentSheet = null,
    ) {
        parent::__construct('GAME_CONFLICT', $message, $previous);
    }

    /**
     * Версия строки, если она известна.
     *
     * @return int|null actual_version.
     */
    public function getCurrentVersion(): ?int
    {
        return $this->currentVersion;
    }

    /**
     * Свежий лист NPC, если конфликт относится к нему.
     *
     * @return array{choices: array<mixed>, sheet: array<mixed>}|null Лист.
     */
    public function getCurrentSheet(): ?array
    {
        return $this->currentSheet;
    }

    /**
     * Версия несовпавшей строки. У конфликта ключа поля нет.
     *
     * @return array<string, int> Детали.
     */
    public function getErrorDetails(): array
    {
        $details = [];
        if ($this->currentVersion !== null) {
            $details['currentVersion'] = $this->currentVersion;
        }

        if ($this->currentSheet !== null) {
            $details['choices'] = $this->currentSheet['choices'];
            $details['sheet'] = $this->currentSheet['sheet'];
        }

        return $details;
    }
}
