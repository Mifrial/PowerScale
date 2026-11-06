<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Exception;

use Throwable;

/**
 * Поля, границы или геометрия карты недопустимы.
 */
final class MapInvalidException extends MapException
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
        string $message = 'Map values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('MAP_INVALID', $message, $previous);
    }
}
