<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Throwable;

/**
 * Недопустимый вход файлового действия.
 */
final class FileInvalidException extends FileException
{
    /**
     * Создаёт ошибку входа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'File request is invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_INVALID', $message, $previous);
    }
}
