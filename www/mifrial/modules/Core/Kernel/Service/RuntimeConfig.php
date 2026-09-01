<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Dto\CacheSettings;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;

/**
 * Снимок local.php для портов Kernel и соседей.
 */
final class RuntimeConfig implements IRuntimeConfig
{
    /**
     * Создаёт снимок конфига.
     *
     * @param bool $debug Признак debug.
     * @param DatabaseSettings $databaseSettings Настройки MySQL.
     * @param CacheSettings $cacheSettings Настройки кэша.
     *
     * @return void
     */
    private function __construct(
        private readonly bool $debug,
        private readonly DatabaseSettings $databaseSettings,
        private readonly CacheSettings $cacheSettings,
    ) {
    }

    /**
     * Собирает снимок из уже прочитанного массива конфига.
     *
     * @param array<string, mixed> $localConfig Локальная конфигурация.
     *
     * @return self Снимок конфига.
     */
    public static function fromLocal(array $localConfig): self
    {
        return new self(
            $localConfig['debug'] === true,
            DatabaseSettings::fromConfig($localConfig['db'] ?? null),
            CacheSettings::fromConfig($localConfig['cache'] ?? null),
        );
    }

    /**
     * Возвращает признак отладочного режима.
     *
     * @return bool true, если debug включён.
     */
    public function debug(): bool
    {
        return $this->debug;
    }

    /**
     * Возвращает срез настроек базы без подключения.
     *
     * @return DatabaseSettings Настройки MySQL.
     */
    public function database(): DatabaseSettings
    {
        return $this->databaseSettings;
    }

    /**
     * Возвращает срез настроек кэша без открытия store.
     *
     * @return CacheSettings Настройки file/redis.
     */
    public function cache(): CacheSettings
    {
        return $this->cacheSettings;
    }

    /**
     * Возвращает драйвер кэша из конфига.
     *
     * @return string Имя драйвера, по умолчанию file.
     */
    public function cacheDriver(): string
    {
        return $this->cacheSettings->driver();
    }
}
