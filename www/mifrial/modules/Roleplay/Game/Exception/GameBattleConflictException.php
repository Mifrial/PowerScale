<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Exception;

use Throwable;

/**
 * Конфликт версии боя или чужого тела ключа.
 */
final class GameBattleConflictException extends GameException
{
    /**
     * Создаёт конфликт.
     *
     * @param int|null $currentVersion Текущая версия боя или null для ключа.
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>}|null $currentSheet
     *     Разрешённая проекция листа.
     * @param array{type: string, id: int}|null $target Конфликтующая wide-цель.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $currentVersion,
        string $message = 'Game battle version conflict',
        ?Throwable $previous = null,
        private readonly ?array $currentSheet = null,
        private readonly ?array $target = null,
    ) {
        parent::__construct('GAME_CONFLICT', $message, $previous);
    }

    /**
     * Версия несовпавшего боя. У конфликта ключа поля нет.
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

        if ($this->target !== null) {
            $details['target'] = $this->target;
        }

        return $details;
    }
}
