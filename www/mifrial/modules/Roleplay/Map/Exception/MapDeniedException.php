<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Exception;

use Throwable;

/**
 * Нет доступа к карте или её правке.
 */
final class MapDeniedException extends MapException
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
        string $message = 'Map access is denied',
        ?Throwable $previous = null,
    ) {
        parent::__construct('MAP_DENIED', $message, $previous);
    }
}
