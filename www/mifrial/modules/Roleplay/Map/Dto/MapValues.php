<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Строгие преобразования полей строки карты.
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
     * Полилиния или полигон из JSON.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Поле.
     * @param int $minimum Минимум точек.
     * @param bool $required Поле обязательно.
     *
     * @return array<int, array{0: int, 1: int}>|null Точки или null.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function path(array $fields, string $key, int $minimum, bool $required): ?array
    {
        return self::points($fields[$key] ?? null, $minimum, $required);
    }

    /**
     * Полилиния или полигон из значения JSON.
     *
     * @param mixed $value Значение.
     * @param int $minimum Минимум точек.
     * @param bool $required Значение обязательно.
     *
     * @return array<int, array{0: int, 1: int}>|null Точки или null.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function points(mixed $value, int $minimum, bool $required): ?array
    {
        if ($value === null && !$required) {
            return null;
        }

        if (!is_array($value) || count($value) < $minimum) {
            throw new MapInvalidException('Map path is invalid');
        }

        $points = [];
        foreach ($value as $point) {
            $points[] = self::point($point);
        }

        return $points;
    }

    /**
     * Ширины ленты по точкам.
     *
     * @param mixed $value Значение JSON.
     * @param int $expected Ожидаемое число точек.
     *
     * @return array<int, int> Пиксели.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function widths(mixed $value, int $expected): array
    {
        if (!is_array($value) || count($value) !== $expected) {
            throw new MapInvalidException('Map line widths are invalid');
        }

        $widths = [];
        foreach ($value as $width) {
            $parsed = self::intValue($width);
            if ($parsed === null || $parsed < 1) {
                throw new MapInvalidException('Map line width is invalid');
            }

            $widths[] = $parsed;
        }

        return $widths;
    }

    /**
     * Ручки Безье по точкам ленты.
     *
     * @param mixed $value Значение JSON.
     * @param int $expected Ожидаемое число точек.
     *
     * @return array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> Ручки.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function handles(mixed $value, int $expected): array
    {
        if ($value === null || $value === []) {
            return array_fill(0, $expected, ['in' => [0, 0], 'out' => [0, 0]]);
        }

        if (!is_array($value) || count($value) !== $expected) {
            throw new MapInvalidException('Map line handles are invalid');
        }

        $handles = [];
        foreach ($value as $handle) {
            if (!is_array($handle)) {
                throw new MapInvalidException('Map line handle is invalid');
            }

            $handles[] = [
                'in' => self::handleOffset($handle['in'] ?? null),
                'out' => self::handleOffset($handle['out'] ?? null),
            ];
        }

        return $handles;
    }

    /**
     * Смещение ручки [dx, dy].
     *
     * @param mixed $value Значение.
     *
     * @return array{0: int, 1: int} Смещение.
     *
     * @throws MapInvalidException Если смещение неверно.
     */
    private static function handleOffset(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new MapInvalidException('Map line handle offset is invalid');
        }

        $x = self::signedIntValue($value[0] ?? null);
        $y = self::signedIntValue($value[1] ?? null);
        if ($x === null || $y === null || abs($x) > 20000 || abs($y) > 20000) {
            throw new MapInvalidException('Map line handle offset is invalid');
        }

        return [$x, $y];
    }

    /**
     * Целое из int или числовой строки.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Целое или null.
     */
    private static function intValue(mixed $value): ?int
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
    private static function signedIntValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Точка [x, y].
     *
     * @param mixed $point Значение.
     *
     * @return array{0: int, 1: int} Точка.
     *
     * @throws MapInvalidException Если точка не пара целых.
     */
    private static function point(mixed $point): array
    {
        if (!is_array($point) || count($point) !== 2) {
            throw new MapInvalidException('Map path point is invalid');
        }

        $x = self::intValue($point[0] ?? null);
        $y = self::intValue($point[1] ?? null);
        if ($x === null || $y === null) {
            throw new MapInvalidException('Map path point is invalid');
        }

        return [$x, $y];
    }
}
