<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Exception;

use Throwable;

/**
 * Значения персонажа недопустимы.
 */
final class CharacterInvalidException extends CharacterException
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
        string $message = 'Character values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('CHARACTER_INVALID', $message, $previous);
    }
}
