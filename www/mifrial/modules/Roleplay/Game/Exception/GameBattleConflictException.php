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
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $currentVersion,
        string $message = 'Game battle version conflict',
        ?Throwable $previous = null,
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
        if ($this->currentVersion === null) {
            return [];
        }

        return ['currentVersion' => $this->currentVersion];
    }
}
