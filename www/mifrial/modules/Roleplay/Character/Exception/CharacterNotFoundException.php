<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Exception;

use Throwable;

/**
 * Строка персонажа, учётки или часов мира не найдена.
 */
final class CharacterNotFoundException extends CharacterException
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
        string $message = 'Character was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('CHARACTER_NOT_FOUND', $message, $previous);
    }
}
