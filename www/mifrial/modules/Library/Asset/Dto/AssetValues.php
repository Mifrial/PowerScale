<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Library\Asset\Exception\AssetInvalidException;

/**
 * Строгие преобразования скалярных полей строки ассета.
 */
final class AssetValues
{
    /**
     * Обязательное целое.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return int Целое.
     *
     * @throws AssetInvalidException Если поля нет или тип неверен.
     */
    public static function requireInt(array $fields, string $key): int
    {
        $value = $fields[$key] ?? null;
        if (!is_int($value)) {
            throw new AssetInvalidException('Asset row has no integer ' . $key);
        }

        return $value;
    }

    /**
     * Необязательное целое.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return int|null Целое или null.
     */
    public static function optionalInt(array $fields, string $key): ?int
    {
        $value = $fields[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * Обязательная строка.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return string Строка.
     *
     * @throws AssetInvalidException Если поля нет или тип неверен.
     */
    public static function requireString(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value)) {
            throw new AssetInvalidException('Asset row has no string ' . $key);
        }

        return $value;
    }

    /**
     * Обязательный bool.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return bool Флаг.
     *
     * @throws AssetInvalidException Если поля нет или тип неверен.
     */
    public static function requireBool(array $fields, string $key): bool
    {
        $value = $fields[$key] ?? null;
        if (!is_bool($value)) {
            throw new AssetInvalidException('Asset row has no bool ' . $key);
        }

        return $value;
    }

    /**
     * Обязательная дата.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return DateTime Дата.
     *
     * @throws AssetInvalidException Если поля нет или тип неверен.
     */
    public static function requireDateTime(array $fields, string $key): DateTime
    {
        $value = $fields[$key] ?? null;
        if (!$value instanceof DateTime) {
            throw new AssetInvalidException('Asset row has no datetime ' . $key);
        }

        return $value;
    }
}
