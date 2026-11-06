<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Exception;

use Throwable;

/**
 * Между выбранными узлами нет проходимого маршрута.
 */
final class MapNoRouteException extends MapException
{
    /**
     * Создаёт ошибку маршрута.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Map route is not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('MAP_NO_ROUTE', $message, $previous);
    }
}
