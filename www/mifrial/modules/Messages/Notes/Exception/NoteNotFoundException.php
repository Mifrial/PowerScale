<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Exception;

use Throwable;

/**
 * Заметка не найдена или недоступна обычному пользователю.
 */
final class NoteNotFoundException extends NoteException
{
    /**
     * Создаёт ошибку отсутствия.
     *
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('NOTE_NOT_FOUND', 'Note was not found', $previous);
    }
}
