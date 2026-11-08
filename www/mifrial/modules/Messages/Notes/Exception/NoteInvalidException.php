<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Exception;

use Throwable;

/**
 * Набор полей или инвариант заметки недопустим.
 */
final class NoteInvalidException extends NoteException
{
    /**
     * Создаёт ошибку значений заметки.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Note values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('NOTE_INVALID', $message, $previous);
    }
}
