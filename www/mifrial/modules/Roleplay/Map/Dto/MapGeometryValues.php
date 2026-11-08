<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Service\MapSpeed;

/**
 * Строгий разбор JSON-геометрии строки карты: точки, ширины, ручки, сегменты.
 */
final class MapGeometryValues
{
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
            $parsed = MapValues::intValue($width);
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
            $segments[] = self::segment($segment);
        }

        return $segments;
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
     * Стиль одного сегмента ленты.
     *
     * @param mixed $value Значение JSON.
     *
     * @return array{speed: int, color: string|null} Стиль.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    private static function segment(mixed $value): array
    {
        if (!is_array($value)) {
            throw new MapInvalidException('Map line segment is invalid');
        }

        $speed = MapValues::intValue($value['speed'] ?? null);
        if ($speed === null || $speed < 0 || $speed > MapSpeed::MAX) {
            throw new MapInvalidException('Map line segment speed is invalid');
        }

        $color = $value['color'] ?? null;
        if ($color !== null && (!is_string($color) || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1)) {
            throw new MapInvalidException('Map line segment color is invalid');
        }

        return ['speed' => $speed, 'color' => $color];
    }

    /**
     * Смещение ручки [dx, dy].
     *
     * @param mixed $value Значение.
     *
     * @return array{0: int, 1: int} Смещение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private static function handleOffset(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new MapInvalidException('Map line handle offset is invalid');
        }

        $x = MapValues::signedIntValue($value[0] ?? null);
        $y = MapValues::signedIntValue($value[1] ?? null);
        if ($x === null || $y === null) {
            throw new MapInvalidException('Map line handle offset is invalid');
        }

        return [$x, $y];
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

        $x = MapValues::intValue($point[0] ?? null);
        $y = MapValues::intValue($point[1] ?? null);
        if ($x === null || $y === null) {
            throw new MapInvalidException('Map path point is invalid');
        }

        return [$x, $y];
    }
}
