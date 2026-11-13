<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Exception;

use Throwable;

/**
 * Ассета или коллекции нет.
 */
final class AssetNotFoundException extends AssetException
{
    /**
     * Создаёт ошибку отсутствия.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Asset was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('ASSET_NOT_FOUND', $message, $previous);
    }
}
