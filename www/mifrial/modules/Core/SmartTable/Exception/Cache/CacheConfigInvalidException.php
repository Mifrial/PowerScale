<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Cache;

/**
 * Непригодный срез cache из RuntimeConfig для store SmartTable.
 */
final class CacheConfigInvalidException extends CacheException
{
    /**
     * Создаёт ошибку конфигурации кэша.
     *
     * @param string $message Текст без секретов.
     *
     * @return void
     */
    public function __construct(string $message = 'SmartTable cache settings are invalid')
    {
        parent::__construct('CACHE_CONFIG_INVALID', $message);
    }
}
