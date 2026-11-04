<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Exception;

use Throwable;

/**
 * Драйвер не смог построить или записать производную.
 */
final class ImageTransformException extends ImageException
{
    /**
     * Создаёт ошибку преобразования.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Image transform failed',
        ?Throwable $previous = null,
    ) {
        parent::__construct('IMAGE_TRANSFORM', $message, $previous);
    }
}
