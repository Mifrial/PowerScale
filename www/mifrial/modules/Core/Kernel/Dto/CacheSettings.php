<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

/**
 * Срез настроек кэша из local.php без открытия store.
 */
final class CacheSettings
{
    /**
     * Создаёт набор полей кэша.
     *
     * @param string $driver Имя драйвера.
     * @param string $path Каталог file-store.
     * @param string $redisHost Хост Redis.
     * @param int $redisPort Порт Redis.
     *
     * @return void
     */
    private function __construct(
        private readonly string $driver,
        private readonly string $path,
        private readonly string $redisHost,
        private readonly int $redisPort,
    ) {
    }

    /**
     * Собирает настройки из ключа cache локального конфига.
     *
     * @param mixed $cacheConfig Значение ключа cache.
     *
     * @return self Настройки; дыры не бросают исключение.
     */
    public static function fromConfig(mixed $cacheConfig): self
    {
        if (!is_array($cacheConfig)) {
            return new self('file', '', '', 0);
        }

        $driver = $cacheConfig['driver'] ?? null;
        $path = $cacheConfig['path'] ?? null;
        $redis = $cacheConfig['redis'] ?? null;
        $redisHost = is_array($redis) ? ($redis['host'] ?? null) : null;
        $redisPort = is_array($redis) ? ($redis['port'] ?? null) : null;

        return new self(
            is_string($driver) && $driver !== '' ? $driver : 'file',
            is_string($path) ? $path : '',
            is_string($redisHost) ? $redisHost : '',
            self::portValue($redisPort),
        );
    }

    /**
     * Возвращает имя драйвера.
     *
     * @return string file или redis, иначе как в конфиге.
     */
    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * Возвращает каталог file-store.
     *
     * @return string Путь или пустая строка.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Возвращает хост Redis.
     *
     * @return string Хост или пустая строка.
     */
    public function redisHost(): string
    {
        return $this->redisHost;
    }

    /**
     * Возвращает порт Redis.
     *
     * @return int Порт или 0.
     */
    public function redisPort(): int
    {
        return $this->redisPort;
    }

    /**
     * Приводит порт к целому.
     *
     * @param mixed $value Сырой порт.
     *
     * @return int Порт или 0.
     */
    private static function portValue(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return 0;
    }
}
