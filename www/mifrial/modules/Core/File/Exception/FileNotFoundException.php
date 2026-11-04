<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Throwable;

/**
 * Строка файла не найдена.
 */
final class FileNotFoundException extends FileException
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
        string $message = 'File row not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_NOT_FOUND', $message, $previous);
    }
}
