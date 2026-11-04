<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Throwable;

/**
 * PHP не принял multipart-файл целиком.
 */
final class FileUploadBrokenException extends FileException
{
    /**
     * Создаёт ошибку приёма.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'File upload is broken',
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_UPLOAD_BROKEN', $message, $previous);
    }
}
