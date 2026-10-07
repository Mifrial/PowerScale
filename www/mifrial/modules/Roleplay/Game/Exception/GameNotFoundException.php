<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Exception;

use Throwable;

/**
 * Строка игры, учётка или мир правил не найдены.
 */
final class GameNotFoundException extends GameException
{
    /**
     * Создаёт ошибку отсутствия строки.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Game was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('GAME_NOT_FOUND', $message, $previous);
    }
}
