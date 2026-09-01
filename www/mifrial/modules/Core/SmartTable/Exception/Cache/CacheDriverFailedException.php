<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Cache;

use Throwable;

/**
 * Драйвер кэша отклонил чтение, запись или сброс.
 */
final class CacheDriverFailedException extends CacheException
{
    /**
     * Создаёт ошибку I/O кэша.
     *
     * @param Throwable|null $previous Исходный throwable драйвера.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('CACHE_DRIVER_FAILED', 'SmartTable cache driver failed', $previous);
    }
}
