<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Exception;

use Throwable;

/**
 * Нет доступа к общей или чужой личной части библиотеки.
 */
final class AssetDeniedException extends AssetException
{
    /**
     * Создаёт ошибку доступа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Asset access is denied',
        ?Throwable $previous = null,
    ) {
        parent::__construct('ASSET_DENIED', $message, $previous);
    }
}
