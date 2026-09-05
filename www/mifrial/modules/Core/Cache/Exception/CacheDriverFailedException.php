<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Exception;

use Throwable;

/**
 * I/O драйвера file или redis.
 */
final class CacheDriverFailedException extends CacheException
{
    /**
     * Создаёт ошибку I/O.
     *
     * @param Throwable|null $previous Исходный throwable.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('CACHE_DRIVER_FAILED', 'Cache driver failed', $previous);
    }
}
