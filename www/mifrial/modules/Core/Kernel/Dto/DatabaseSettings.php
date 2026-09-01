<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

/**
 * Срез настроек MySQL из local.php без подключения к серверу.
 */
final class DatabaseSettings
{
    /**
     * Создаёт набор полей соединения.
     *
     * @param array<string, mixed> $fields Поля соединения.
     *
     * @return void
     */
    private function __construct(
        private readonly array $fields,
    ) {
    }

    /**
     * Собирает настройки из ключа db локального конфига.
     *
     * @param mixed $databaseConfig Значение ключа db.
     *
     * @return self Настройки; дыры не бросают исключение.
     */
    public static function fromConfig(mixed $databaseConfig): self
    {
        if (!is_array($databaseConfig)) {
            return self::fromFields('', 0, '', '', '', '', false);
        }

        return self::fromFields(
            self::stringValue($databaseConfig['host'] ?? null),
            self::portValue($databaseConfig['port'] ?? null),
            self::stringValue($databaseConfig['database'] ?? null),
            self::stringValue($databaseConfig['username'] ?? null),
            self::stringValue($databaseConfig['password'] ?? null),
            self::stringValue($databaseConfig['charset'] ?? null),
            array_key_exists('dsn', $databaseConfig),
            self::stringValue($databaseConfig['collation'] ?? null),
            self::stringValue($databaseConfig['timezone'] ?? null),
        );
    }

    /**
     * Собирает настройки из отдельных полей.
     *
     * @param string $host Хост.
     * @param int $port Порт.
     * @param string $database Имя базы.
     * @param string $username Пользователь.
     * @param string $password Пароль.
     * @param string $charset Кодировка.
     * @param bool $hasLegacyDsn Признак ключа dsn.
     * @param string $collation Collation соединения.
     * @param string $timezone Часовой пояс сессии MySQL.
     *
     * @return self Настройки соединения.
     */
    public static function fromFields(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        string $charset,
        bool $hasLegacyDsn = false,
        string $collation = '',
        string $timezone = '',
    ): self {
        return new self([
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
            'collation' => $collation,
            'timezone' => $timezone,
            'hasLegacyDsn' => $hasLegacyDsn,
        ]);
    }

    /**
     * Возвращает хост MySQL.
     *
     * @return string Хост или пустая строка.
     */
    public function host(): string
    {
        return $this->fields['host'];
    }

    /**
     * Возвращает порт.
     *
     * @return int Порт или 0, если не задан.
     */
    public function port(): int
    {
        return $this->fields['port'];
    }

    /**
     * Возвращает имя базы.
     *
     * @return string Имя базы или пустая строка.
     */
    public function database(): string
    {
        return $this->fields['database'];
    }

    /**
     * Возвращает пользователя.
     *
     * @return string Имя пользователя.
     */
    public function username(): string
    {
        return $this->fields['username'];
    }

    /**
     * Возвращает пароль.
     *
     * @return string Пароль.
     */
    public function password(): string
    {
        return $this->fields['password'];
    }

    /**
     * Возвращает кодировку.
     *
     * @return string Charset или пустая строка.
     */
    public function charset(): string
    {
        return $this->fields['charset'];
    }

    /**
     * Возвращает charset, collation и timezone сессии MySQL.
     *
     * Значения задаёт local.php сайта. Пустой ключ остаётся пустым.
     *
     * @return array{charset: string, collation: string, timezone: string} Локаль соединения.
     */
    public function mysqlLocale(): array
    {
        return [
            'charset' => $this->fields['charset'],
            'collation' => $this->fields['collation'],
            'timezone' => $this->fields['timezone'],
        ];
    }

    /**
     * Возвращает признак запрещённого ключа dsn.
     *
     * @return bool true, если в конфиге был dsn.
     */
    public function hasLegacyDsn(): bool
    {
        return $this->fields['hasLegacyDsn'];
    }

    /**
     * Приводит значение к строке настройки.
     *
     * @param mixed $value Сырое значение ключа.
     *
     * @return string Строка или пусто, если тип не строка.
     */
    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Приводит порт к целому.
     *
     * @param mixed $value Сырой порт.
     *
     * @return int Порт или 0, если значение непригодно.
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
