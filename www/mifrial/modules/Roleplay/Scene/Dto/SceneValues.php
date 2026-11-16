<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;

/**
 * Строгие преобразования скалярных полей строки локации.
 */
final class SceneValues
{
    /**
     * Обязательное целое.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return int Целое.
     *
     * @throws SceneInvalidException Если поля нет или тип неверен.
     */
    public static function requireInt(array $fields, string $key): int
    {
        $value = self::intValue($fields[$key] ?? null);
        if ($value === null) {
            throw new SceneInvalidException('Scene row has no integer ' . $key);
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
     * @throws SceneInvalidException Если поля нет или тип неверен.
     */
    public static function requireString(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value)) {
            throw new SceneInvalidException('Scene row has no string ' . $key);
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
     * @throws SceneInvalidException Если поля нет или тип неверен.
     */
    public static function requireBool(array $fields, string $key): bool
    {
        $value = $fields[$key] ?? null;
        if (!is_bool($value)) {
            throw new SceneInvalidException('Scene row has no bool ' . $key);
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
     * @throws SceneInvalidException Если поля нет или тип неверен.
     */
    public static function requireDateTime(array $fields, string $key): DateTime
    {
        $value = $fields[$key] ?? null;
        if (!$value instanceof DateTime) {
            throw new SceneInvalidException('Scene row has no datetime ' . $key);
        }

        return $value;
    }

    /**
     * Обязательный JSON-массив.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return array<string|int, mixed> Массив.
     *
     * @throws SceneInvalidException Если поля нет или тип неверен.
     */
    public static function requireArray(array $fields, string $key): array
    {
        $value = $fields[$key] ?? null;
        if (!is_array($value)) {
            throw new SceneInvalidException('Scene row has no array ' . $key);
        }

        return $value;
    }

    /**
     * Необязательный JSON-массив.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return array<string|int, mixed> Массив; пустой, если поля нет.
     */
    public static function optionalArray(array $fields, string $key): array
    {
        $value = $fields[$key] ?? null;

        return is_array($value) ? $value : [];
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

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
