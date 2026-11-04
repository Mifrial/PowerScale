<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Throwable;

/**
 * Картинка не читается или не проходит пределы размеров.
 */
final class FileImageInvalidException extends FileException
{
    /**
     * Создаёт ошибку изображения.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'File image is invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_IMAGE_INVALID', $message, $previous);
    }
}
