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
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $currentVersion,
        string $message = 'Game economy version conflict',
        ?Throwable $previous = null,
    ) {
        parent::__construct('GAME_CONFLICT', $message, $previous);
    }

    /**
     * Версия несовпавшей строки. У конфликта ключа поля нет.
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
