<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Exception;

use Throwable;

/**
 * Карты, варианта, узла, области или ребра нет.
 */
final class MapNotFoundException extends MapException
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
        string $message = 'Map was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('MAP_NOT_FOUND', $message, $previous);
    }
}
