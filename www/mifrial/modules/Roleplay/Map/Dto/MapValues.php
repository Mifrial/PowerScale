<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Строгие преобразования скалярных полей строки карты.
 */
final class MapValues
{
    /**
     * Обязательное целое.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return int Целое.
     *
     * @throws MapInvalidException Если поля нет или тип неверен.
     */
    public static function requireInt(array $fields, string $key): int
    {
        $value = self::intValue($fields[$key] ?? null);
        if ($value === null) {
            throw new MapInvalidException('Map row has no integer ' . $key);
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
        return self::intValue($fields[$key] ?? null);
    }

    /**
     * Обязательная строка.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return string Строка.
     *
     * @throws MapInvalidException Если поля нет или тип неверен.
     */
    public static function requireString(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value)) {
            throw new MapInvalidException('Map row has no string ' . $key);
        }

        return $value;
    }

    /**
     * Необязательная строка.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return string|null Строка или null.
     */
    public static function optionalString(array $fields, string $key): ?string
    {
        $value = $fields[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Обязательный bool.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return bool Флаг.
     *
     * @throws MapInvalidException Если поля нет или тип неверен.
     */
    public static function requireBool(array $fields, string $key): bool
    {
        $value = $fields[$key] ?? null;
        if (!is_bool($value)) {
            throw new MapInvalidException('Map row has no bool ' . $key);
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
     * @throws MapInvalidException Если поля нет или тип неверен.
     */
    public static function requireDateTime(array $fields, string $key): DateTime
    {
        $value = $fields[$key] ?? null;
        if (!$value instanceof DateTime) {
            throw new MapInvalidException('Map row has no datetime ' . $key);
        }

        return $value;
    }

    /**
     * Целое из int или десятичной строки.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Целое или null.
     */
    public static function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Знаковое целое из int или числовой строки.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Целое или null.
     */
    public static function signedIntValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
