<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Service\MapDecor;
use Mifrial\Roleplay\Map\Service\MapSpeed;

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
     * Стили сегментов ленты.
     *
     * @param mixed $value Значение JSON.
     * @param int $expected Ожидаемое число сегментов.
     *
     * @return array<int, array{speed: int, color: string|null}> Стили; пусто — по умолчанию.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function segments(mixed $value, int $expected): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || count($value) !== $expected) {
            throw new MapInvalidException('Map line segments are invalid');
        }

        $segments = [];
        foreach ($value as $segment) {
            if (!is_array($segment)) {
                throw new MapInvalidException('Map line segment is invalid');
            }

            $speed = self::intValue($segment['speed'] ?? null);
            if ($speed === null || $speed < 0 || $speed > MapSpeed::MAX) {
                throw new MapInvalidException('Map line segment speed is invalid');
            }

            $color = $segment['color'] ?? null;
            if ($color !== null) {
                if (!is_string($color) || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
                    throw new MapInvalidException('Map line segment color is invalid');
                }
            }

            $segments[] = ['speed' => $speed, 'color' => $color];
        }

        return $segments;
    }

    /**
     * Визуальный декор ленты.
     *
     * @param mixed $value Значение из строки.
     *
     * @return array<string, mixed> Декор; пусто — без декора.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function decor(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException('Map line decor is invalid');
        }

        foreach (array_keys($value) as $unknown) {
            if (!in_array($unknown, ['flow', 'fillOpacity', 'depth', 'depthWidth'], true)) {
                throw new MapInvalidException('Map line decor has an unknown key');
            }
        }

        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = self::decorInt($value['fillOpacity'], 0, 100);
        }

        if (array_key_exists('depth', $value)) {
            $decor['depth'] = self::decorInt($value['depth'], 0, 100);
        }

        if (array_key_exists('depthWidth', $value)) {
            $decor['depthWidth'] = self::decorInt($value['depthWidth'], 20, 100);
        }

        $flow = $value['flow'] ?? null;
        if ($flow === null) {
            return $decor;
        }

        if (!is_array($flow)) {
            throw new MapInvalidException('Map line flow is invalid');
        }

        $enabled = $flow['enabled'] ?? false;
        if (!is_bool($enabled)) {
            throw new MapInvalidException('Map line flow enabled is invalid');
        }

        $speed = self::decorInt($flow['speed'] ?? 40, 1, 600);
        $density = self::decorInt($flow['density'] ?? 50, 0, 100);
        $opacity = self::decorInt($flow['opacity'] ?? 18, 0, 100);
        $direction = self::decorInt($flow['direction'] ?? 1, -1, 1);
        if ($direction === 0) {
            throw new MapInvalidException('Map line flow direction is invalid');
        }

        $seed = self::decorInt($flow['seed'] ?? 0, 0, 1000000);
        $color = $flow['color'] ?? '#ffffff';
        if (!is_string($color) || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new MapInvalidException('Map line flow color is invalid');
        }

        $decor['flow'] = [
            'enabled' => $enabled,
            'speed' => $speed,
            'density' => $density,
            'opacity' => $opacity,
            'color' => $color,
            'direction' => $direction,
            'seed' => $seed,
        ];

        return $decor;
    }

    /**
     * Визуальный декор области.
     *
     * @param mixed $value Значение из строки.
     *
     * @return array<string, mixed> Декор; пусто — без декора.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function areaDecor(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException('Map area decor is invalid');
        }

        $allowed = ['fillOpacity', 'set', 'density', 'scale', 'jitter', 'seed'];
        foreach (array_keys($value) as $unknown) {
            if (!in_array($unknown, $allowed, true)) {
                throw new MapInvalidException('Map area decor has an unknown key');
            }
        }

        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = self::decorInt($value['fillOpacity'], 0, 100);
        }

        $set = $value['set'] ?? null;
        if ($set === null) {
            if (count($decor) !== count($value)) {
                throw new MapInvalidException('Map area decor has settings without a set');
            }

            return $decor;
        }

        if (!is_string($set) || !in_array($set, MapDecor::SETS, true)) {
            throw new MapInvalidException('Map area decor set is invalid');
        }

        $decor['set'] = $set;
        if (array_key_exists('density', $value)) {
            $decor['density'] = self::decorInt($value['density'], 0, MapDecor::DENSITY_MAX);
        }

        if (array_key_exists('scale', $value)) {
            $decor['scale'] = self::decorInt($value['scale'], MapDecor::SCALE_MIN, MapDecor::SCALE_MAX);
        }

        if (array_key_exists('jitter', $value)) {
            $decor['jitter'] = self::decorInt($value['jitter'], 0, MapDecor::JITTER_MAX);
        }

        if (array_key_exists('seed', $value)) {
            $decor['seed'] = self::decorInt($value['seed'], 0, MapDecor::SEED_MAX);
        }

        return $decor;
    }

    /**
     * Целое декора в диапазоне.
     *
     * @param mixed $value Значение.
     * @param int $min Минимум.
     * @param int $max Максимум.
     *
     * @return int Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private static function decorInt(mixed $value, int $min, int $max): int
    {
        $int = self::intValue($value);
        if ($int === null || $int < $min || $int > $max) {
            throw new MapInvalidException('Map line flow value is invalid');
        }

        return $int;
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
