<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Exception;

use Throwable;

/**
 * Параметры производной вне допустимого диапазона.
 */
final class ImageSpecInvalidException extends ImageException
{
    /**
     * Создаёт ошибку спецификации.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Image transform spec is invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('IMAGE_SPEC_INVALID', $message, $previous);
    }
}
