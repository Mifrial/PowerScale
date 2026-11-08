<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Разбор и проверка геометрии ленты и контура в снимке карты.
 */
final class MapSaveGeometryFields
{
    /**
     * Создаёт разбор.
     *
     * @param MapSaveFields $fields Скалярные поля.
     *
     * @return void
     */
    public function __construct(
        private readonly MapSaveFields $fields,
    ) {
    }

    /**
     * Точки полилинии или полигона.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $min Минимум точек.
     * @param bool $allowEmpty Пустой список допустим.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function pointsValue(array $row, string $key, string $path, int $min, bool $allowEmpty = false): array
    {
        $value = $row[$key] ?? null;
        if (!is_array($value)) {
            throw new MapInvalidException($path . ' is not a list');
        }

        if ($value === [] && $allowEmpty) {
            return [];
        }

        if (count($value) < $min) {
            throw new MapInvalidException($path . ' has too few points');
        }

        $points = [];
        foreach ($value as $point) {
            $points[] = $this->pointValue($point, $path);
        }

        return $points;
    }

    /**
     * Ширины ленты.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $expected Ожидаемое число.
     *
     * @return array<int, int> Ширины.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function widthsValue(array $row, string $key, string $path, int $expected): array
    {
        $value = $row[$key] ?? null;
        if (!is_array($value) || count($value) !== $expected) {
            throw new MapInvalidException($path . ' does not match the points');
        }

        $widths = [];
        foreach ($value as $width) {
            if (!is_int($width) || $width < 1 || $width > 20000) {
                throw new MapInvalidException($path . ' has an invalid width');
            }

            $widths[] = $width;
        }

        return $widths;
    }

    /**
     * Ручки Безье ленты.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $expected Ожидаемое число.
     *
     * @return array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> Ручки.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function handlesValue(array $row, string $key, string $path, int $expected): array
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === []) {
            return array_fill(0, $expected, ['in' => [0, 0], 'out' => [0, 0]]);
        }

        if (!is_array($value) || count($value) !== $expected) {
            throw new MapInvalidException($path . ' does not match the points');
        }

        $handles = [];
        foreach ($value as $handle) {
            if (!is_array($handle)) {
                throw new MapInvalidException($path . ' has an invalid handle');
            }

            $handles[] = [
                'in' => $this->handleOffset($handle['in'] ?? null, $path),
                'out' => $this->handleOffset($handle['out'] ?? null, $path),
            ];
        }

        return $handles;
    }

    /**
     * Стили сегментов ленты.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $expected Ожидаемое число сегментов.
     *
     * @return array<int, array{speed: int, color: string|null}> Стили; пусто — по умолчанию.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function segmentsValue(array $row, string $key, string $path, int $expected): array
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || count($value) !== $expected) {
            throw new MapInvalidException($path . ' does not match the points');
        }

        $segments = [];
        foreach ($value as $index => $segment) {
            $segments[] = $this->segmentValue($segment, $path . '[' . $index . ']');
        }

        return $segments;
    }

    /**
     * Стиль одного сегмента ленты.
     *
     * @param mixed $segment Значение сегмента.
     * @param string $path Путь.
     *
     * @return array{speed: int, color: string|null} Стиль.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    private function segmentValue(mixed $segment, string $path): array
    {
        if (!is_array($segment)) {
            throw new MapInvalidException($path . ' is invalid');
        }

        $color = $this->fields->optionalStringValue($segment, 'color', $path . '.color');
        $color = $color === null ? null : trim($color);
        if ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new MapInvalidException($path . '.color is invalid');
        }

        return [
            'speed' => $this->fields->intValue($segment, 'speed', $path . '.speed', 0, MapSpeed::MAX),
            'color' => $color,
        ];
    }

    /**
     * Смещение ручки.
     *
     * @param mixed $value Значение.
     * @param string $path Путь.
     *
     * @return array{0: int, 1: int} Смещение.
     *
     * @throws MapInvalidException Если смещение неверно.
     */
    private function handleOffset(mixed $value, string $path): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new MapInvalidException($path . ' has an invalid handle');
        }

        $x = $value[0] ?? null;
        $y = $value[1] ?? null;
        $x = $this->offsetValue($x, $path);
        $y = $this->offsetValue($y, $path);

        return [$x, $y];
    }

    /**
     * Знаковое смещение ручки.
     *
     * @param mixed $value Значение.
     * @param string $path Путь.
     *
     * @return int Смещение.
     *
     * @throws MapInvalidException Если смещение неверно.
     */
    private function offsetValue(mixed $value, string $path): int
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (!is_int($value) || abs($value) > 20000) {
            throw new MapInvalidException($path . ' has an invalid handle');
        }

        return $value;
    }

    /**
     * Точка [x, y].
     *
     * @param mixed $point Значение.
     * @param string $path Путь.
     *
     * @return array{0: int, 1: int} Точка.
     *
     * @throws MapInvalidException Если точка неверна.
     */
    private function pointValue(mixed $point, string $path): array
    {
        if (!is_array($point) || count($point) !== 2) {
            throw new MapInvalidException($path . ' has an invalid point');
        }

        $x = $point[0] ?? null;
        $y = $point[1] ?? null;
        if (!is_int($x) || !is_int($y)) {
            throw new MapInvalidException($path . ' has an invalid point');
        }

        $this->assertCoordinate($x, $path);
        $this->assertCoordinate($y, $path);

        return [$x, $y];
    }

    /**
     * Проверяет координату.
     *
     * @param int $value Значение.
     * @param string $path Путь.
     *
     * @return void
     *
     * @throws MapInvalidException Если координата вне мира.
     */
    private function assertCoordinate(int $value, string $path): void
    {
        if ($value < 0 || $value > 20000) {
            throw new MapInvalidException($path . ' has an invalid point');
        }
    }
}
