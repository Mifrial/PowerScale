<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Exception;

use Throwable;

/**
 * Пространство или ревизия не найдены.
 */
final class SpaceNotFoundException extends SpaceException
{
    /**
     * Создаёт ошибку отсутствия строки.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Space record was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('SPACE_NOT_FOUND', $message, $previous);
    }
}
