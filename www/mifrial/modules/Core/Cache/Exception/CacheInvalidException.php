<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Exception;

/**
 * Непригодный вызов store или дырявый конфиг.
 */
final class CacheInvalidException extends CacheException
{
    /**
     * Создаёт ошибку аргумента или непригодного store.
     *
     * @param string $message Текст без секретов.
     *
     * @return void
     */
    public function __construct(string $message = 'Cache store call is invalid')
    {
        parent::__construct('CACHE_INVALID', $message);
    }
}
