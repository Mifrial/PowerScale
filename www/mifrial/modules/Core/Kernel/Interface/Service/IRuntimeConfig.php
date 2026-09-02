<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;

/**
 * Локальная конфигурация процесса для портов модулей.
 */
interface IRuntimeConfig
{
    /**
     * Возвращает признак отладочного режима.
     *
     * @return bool true, если debug включён.
     */
    public function debug(): bool;

    /**
     * Возвращает срез настроек базы без подключения.
     *
     * @return DatabaseSettings Настройки MySQL.
     */
    public function database(): DatabaseSettings;

    /**
     * Возвращает срез настроек кэша без открытия store.
     *
     * @return CacheSettings Настройки file/redis.
     */
    public function cache(): CacheSettings;

    /**
     * Возвращает драйвер кэша из конфига.
     *
     * @return string Имя драйвера, по умолчанию file.
     */
    public function cacheDriver(): string;

    /**
     * Возвращает срез ключа local.php.
     *
     * @param string $name Имя верхнего ключа.
     *
     * @return mixed Значение или null, если ключа нет.
     */
    public function section(string $name): mixed;
}
