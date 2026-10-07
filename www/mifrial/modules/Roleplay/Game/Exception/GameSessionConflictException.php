<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Exception;

use Throwable;

/**
 * Текущая сессия уже есть. Это не конфликт версий листа.
 */
final class GameSessionConflictException extends GameException
{
    /**
     * Создаёт конфликт повторного старта.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Game session is already running',
        ?Throwable $previous = null,
    ) {
        parent::__construct('GAME_CONFLICT', $message, $previous);
    }
}
