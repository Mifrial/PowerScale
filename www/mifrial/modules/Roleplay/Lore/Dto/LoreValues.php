<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

/**
 * Строгие преобразования скалярных полей строк кодекса.
 */
final class LoreValues
{
    /**
     * Обязательное целое.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return int Целое.
     *
     * @throws LoreInvalidException Если поля нет или тип неверен.
     */
    public static function requireInt(array $fields, string $key): int
    {
        $value = self::intValue($fields[$key] ?? null);
        if ($value === null) {
            throw new LoreInvalidException('Lore row has no integer ' . $key);
        }

        return $value;
    }

    /**
     * Обязательная строка.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return string Строка.
     *
     * @throws LoreInvalidException Если поля нет или тип неверен.
     */
    public static function requireString(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value)) {
            throw new LoreInvalidException('Lore row has no string ' . $key);
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
     * @throws LoreInvalidException Если поля нет или тип неверен.
     */
    public static function requireBool(array $fields, string $key): bool
    {
        $value = $fields[$key] ?? null;
        if (!is_bool($value)) {
            throw new LoreInvalidException('Lore row has no bool ' . $key);
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
     * @throws LoreInvalidException Если поля нет или тип неверен.
     */
    public static function requireDateTime(array $fields, string $key): DateTime
    {
        $value = $fields[$key] ?? null;
        if (!$value instanceof DateTime) {
            throw new LoreInvalidException('Lore row has no datetime ' . $key);
        }

        return $value;
    }

    /**
     * Список целых из mfv-поля.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     *
     * @return array<int, int> Идентификаторы.
     *
     * @throws LoreInvalidException Если поле не список целых.
     */
    public static function requireIntList(array $fields, string $key): array
    {
        $value = $fields[$key] ?? null;
        if (!is_array($value)) {
            throw new LoreInvalidException('Lore row has no list ' . $key);
        }

        $list = [];
        foreach ($value as $item) {
            $intValue = self::intValue($item);
            if ($intValue === null) {
                throw new LoreInvalidException('Lore row has a non-integer in ' . $key);
            }

            $list[] = $intValue;
        }

        return $list;
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
}
