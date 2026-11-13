<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Exception;

use Throwable;

/**
 * Поля, коллекция или границы запроса недопустимы.
 */
final class AssetInvalidException extends AssetException
{
    /**
     * Создаёт ошибку значений.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Asset values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('ASSET_INVALID', $message, $previous);
    }
}
