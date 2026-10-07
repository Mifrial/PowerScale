<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Exception;

use Throwable;

/**
 * Значения игры недопустимы.
 */
final class GameInvalidException extends GameException
{
    /**
     * Создаёт ошибку недопустимого входа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Game values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('GAME_INVALID', $message, $previous);
    }
}
