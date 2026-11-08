<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Разбор и проверка полей снимка карты.
 *
 * Ошибки содержат путь к полю (`nodes[3].name`), чтобы UI подсветил объект.
 */
final class MapSaveFields
{
    /**
     * Идентификатор сущности: целое, не ноль; отрицательный — новая.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если id неверен.
     */
    public function identifier(array $row, string $key, string $path): int
    {
        $value = $this->intValue($row, $key, $path, PHP_INT_MIN);
        if ($value === 0) {
            throw new MapInvalidException($path . ' is zero');
        }

        return $value;
    }

    /**
     * Необязательный идентификатор: null или ненулевое целое; отрицательный — новый.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return int|null Id или null.
     *
     * @throws MapInvalidException Если id неверен.
     */
    public function optionalIdentifier(array $row, string $key, string $path): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->identifier($row, $key, $path);
    }

    /**
     * Целое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $min Минимум.
     * @param int|null $max Максимум.
     *
     * @return int Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    public function intValue(array $row, string $key, string $path, int $min, ?int $max = null): int
    {
        $value = $row[$key] ?? null;
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (!is_int($value)) {
            throw new MapInvalidException($path . ' is not an integer');
        }

        if ($value < $min || ($max !== null && $value > $max)) {
            throw new MapInvalidException($path . ' is out of range');
        }

        return $value;
    }

    /**
     * Необязательное целое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $min Минимум.
     *
     * @return int|null Значение или null.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    public function optionalIntValue(array $row, string $key, string $path, int $min): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->intValue($row, $key, $path, $min);
    }

    /**
     * Строковое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string Значение.
     *
     * @throws MapInvalidException Если значение не строка.
     */
    public function stringValue(array $row, string $key, string $path): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new MapInvalidException($path . ' is not a string');
        }

        return $value;
    }

    /**
     * Необязательное строковое поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string|null Значение или null.
     *
     * @throws MapInvalidException Если значение не строка.
     */
    public function optionalStringValue(array $row, string $key, string $path): ?string
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return $this->stringValue($row, $key, $path);
    }

    /**
     * Булево поле.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return bool Значение.
     *
     * @throws MapInvalidException Если значение не bool.
     */
    public function boolValue(array $row, string $key, string $path): bool
    {
        $value = $row[$key] ?? null;
        if (!is_bool($value)) {
            throw new MapInvalidException($path . ' is not a boolean');
        }

        return $value;
    }

    /**
     * Цвет `#rrggbb`.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string Цвет.
     *
     * @throws MapInvalidException Если цвет неверен.
     */
    public function colorValue(array $row, string $key, string $path): string
    {
        $color = trim($this->stringValue($row, $key, $path));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new MapInvalidException($path . ' is invalid');
        }

        return $color;
    }

    /**
     * Цвет границы: пусто — границы нет, иначе `#rrggbb`.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return string Цвет или пустая строка.
     *
     * @throws MapInvalidException Если цвет неверен.
     */
    public function strokeValue(array $row, string $key, string $path): string
    {
        $color = trim($this->stringValue($row, $key, $path));
        if ($color === '') {
            return '';
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new MapInvalidException($path . ' is invalid');
        }

        return strtolower($color);
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
            $segmentPath = $path . '[' . $index . ']';
            if (!is_array($segment)) {
                throw new MapInvalidException($segmentPath . ' is invalid');
            }

            $color = $this->optionalStringValue($segment, 'color', $segmentPath . '.color');
            $color = $color === null ? null : trim($color);
            if ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
                throw new MapInvalidException($segmentPath . '.color is invalid');
            }

            $segments[] = [
                'speed' => $this->intValue($segment, 'speed', $segmentPath . '.speed', 0, MapSpeed::MAX),
                'color' => $color,
            ];
        }

        return $segments;
    }

    /**
     * Декор ленты: пусто или спокойное течение.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array{flow?: array{enabled: bool, speed: int, density: int, opacity: int,
     *     color: string, direction: int, seed: int}, fillOpacity?: int, depth?: int,
     *     depthWidth?: int} Декор.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function decorValue(array $row, string $key, string $path): array
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException($path . ' is invalid');
        }

        $allowed = ['flow', 'fillOpacity', 'depth', 'depthWidth'];
        foreach (array_keys($value) as $unknown) {
            if (!in_array($unknown, $allowed, true)) {
                throw new MapInvalidException($path . ' has an unknown key');
            }
        }

        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = $this->intValue($value, 'fillOpacity', $path . '.fillOpacity', 0, 100);
        }

        if (array_key_exists('depth', $value)) {
            $decor['depth'] = $this->intValue($value, 'depth', $path . '.depth', 0, 100);
        }

        if (array_key_exists('depthWidth', $value)) {
            $decor['depthWidth'] = $this->intValue($value, 'depthWidth', $path . '.depthWidth', 20, 100);
        }

        $flow = $value['flow'] ?? null;
        if ($flow === null) {
            return $decor;
        }

        if (!is_array($flow)) {
            throw new MapInvalidException($path . '.flow is invalid');
        }

        $flowPath = $path . '.flow';
        $flowAllowed = ['enabled', 'speed', 'density', 'opacity', 'color', 'direction', 'seed'];
        foreach (array_keys($flow) as $unknown) {
            if (!in_array($unknown, $flowAllowed, true)) {
                throw new MapInvalidException($flowPath . ' has an unknown key');
            }
        }

        $direction = $this->flowInt($flow, 'direction', $flowPath . '.direction', -1, 1, 1);
        if ($direction === 0) {
            throw new MapInvalidException($flowPath . '.direction is invalid');
        }

        $decor['flow'] = [
            'enabled' => $this->flowBool($flow, 'enabled', $flowPath . '.enabled'),
            'speed' => $this->flowInt($flow, 'speed', $flowPath . '.speed', 1, 600, 40),
            'density' => $this->flowInt($flow, 'density', $flowPath . '.density', 0, 100, 50),
            'opacity' => $this->flowInt($flow, 'opacity', $flowPath . '.opacity', 0, 100, 18),
            'color' => array_key_exists('color', $flow)
                ? $this->colorValue($flow, 'color', $flowPath . '.color')
                : '#ffffff',
            'direction' => $direction,
            'seed' => $this->flowInt($flow, 'seed', $flowPath . '.seed', 0, 1000000, 0),
        ];

        return $decor;
    }

    /**
     * Декор области: пока только плотность заливки.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array{fillOpacity?: int} Декор.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function areaDecorValue(array $row, string $key, string $path): array
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException($path . ' is invalid');
        }

        $allowed = ['fillOpacity', 'set', 'density', 'scale', 'jitter', 'seed'];
        foreach (array_keys($value) as $unknown) {
            if (!in_array($unknown, $allowed, true)) {
                throw new MapInvalidException($path . ' has an unknown key');
            }
        }

        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = $this->intValue($value, 'fillOpacity', $path . '.fillOpacity', 0, 100);
        }

        $set = $value['set'] ?? null;
        if ($set === null) {
            if (count($decor) !== count($value)) {
                throw new MapInvalidException($path . ' has settings without a set');
            }

            return $decor;
        }

        if (!is_string($set) || !in_array($set, MapDecor::SETS, true)) {
            throw new MapInvalidException($path . '.set is invalid');
        }

        $decor['set'] = $set;
        if (array_key_exists('density', $value)) {
            $decor['density'] = $this->intValue($value, 'density', $path . '.density', 0, MapDecor::DENSITY_MAX);
        }

        if (array_key_exists('scale', $value)) {
            $decor['scale'] = $this->intValue(
                $value,
                'scale',
                $path . '.scale',
                MapDecor::SCALE_MIN,
                MapDecor::SCALE_MAX,
            );
        }

        if (array_key_exists('jitter', $value)) {
            $decor['jitter'] = $this->intValue($value, 'jitter', $path . '.jitter', 0, MapDecor::JITTER_MAX);
        }

        if (array_key_exists('seed', $value)) {
            $decor['seed'] = $this->intValue($value, 'seed', $path . '.seed', 0, MapDecor::SEED_MAX);
        }

        return $decor;
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
     * Целое поле течения с умолчанием.
     *
     * @param array<string|int, mixed> $flow Течение.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $min Минимум.
     * @param int $max Максимум.
     * @param int $default Умолчание.
     *
     * @return int Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private function flowInt(array $flow, string $key, string $path, int $min, int $max, int $default): int
    {
        if (!array_key_exists($key, $flow)) {
            return $default;
        }

        return $this->intValue($flow, $key, $path, $min, $max);
    }

    /**
     * Логическое поле течения с умолчанием.
     *
     * @param array<string|int, mixed> $flow Течение.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return bool Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private function flowBool(array $flow, string $key, string $path): bool
    {
        if (!array_key_exists($key, $flow)) {
            return false;
        }

        return $this->boolValue($flow, $key, $path);
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
