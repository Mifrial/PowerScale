<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Exception;

use Throwable;

/**
 * Файл ассета отсутствует, архивный или не картинка допустимого типа.
 */
final class AssetFileInvalidException extends AssetException
{
    /**
     * Создаёт ошибку файла.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Asset file is invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('ASSET_FILE_INVALID', $message, $previous);
    }
}
