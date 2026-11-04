<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Exception;

use Throwable;

/**
 * Исходник не читается или его формат вне политики модуля.
 */
final class ImageSourceInvalidException extends ImageException
{
    /**
     * Создаёт ошибку источника.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Image source is invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('IMAGE_INVALID', $message, $previous);
    }
}
