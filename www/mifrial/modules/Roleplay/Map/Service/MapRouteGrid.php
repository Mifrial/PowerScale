<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

/**
 * Растровая проходимость варианта.
 *
 * Слой поверхности задаёт скорость, лента перекрывает поверхность,
 * объект сверху пишет свою скорость. Ноль блокирует, земля без разметки проходима.
 *
 * @phpstan-type Field array{width: int, height: int, cell: int, columns: int, rows: int, speeds: array<int, int>}
 * @phpstan-type Contour array{id: int, path: array<int, array{0: int, 1: int}>,
 *     handles: array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}>, speed: int, sortOrder: int}
 * @phpstan-type Area array{id: int, path: array<int, array{0: int, 1: int}>, speed: int, sortOrder: int}
 * @phpstan-type Ribbon array{id: int, points: array<int, array{0: int, 1: int}>, widths: array<int, int>,
 *     handles: array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}>,
 *     segmentSpeeds: array<int, int>, defaultSpeed: int, sortOrder: int}
 */
final class MapRouteGrid
{
    private const TARGET_CELLS = 150000;

    private const DEFAULT_SPEED = MapSpeed::DEFAULT;

    /**
     * Создаёт сетку.
     *
     * @param MapGeometry $geometry Геометрия.
     *
     * @return void
     */
    public function __construct(
        private readonly MapGeometry $geometry,
    ) {
    }

    /**
     * Строит поле скоростей.
     *
     * @param int $width Ширина мира.
     * @param int $height Высота мира.
     * @param array<int, Contour> $surfaces Поверхности.
     * @param array<int, Ribbon> $ribbons Ленты.
     * @param array<int, Contour> $objects Объекты.
     *
     * @return Field Поле.
     */
    public function build(int $width, int $height, array $surfaces, array $ribbons, array $objects): array
    {
        $width = max(1, min(20000, $width));
        $height = max(1, min(20000, $height));
        $cell = $this->cellSize($width, $height);
        $columns = (int) ceil($width / $cell);
        $rows = (int) ceil($height / $cell);
        $field = [
            'width' => $width,
            'height' => $height,
            'cell' => $cell,
            'columns' => max(1, $columns),
            'rows' => max(1, $rows),
            'speeds' => array_fill(0, max(1, $columns) * max(1, $rows), -1),
        ];

        $this->paintContours($field, $this->flatten($surfaces));
        $this->paintRibbons($field, $ribbons);
        $this->paintContours($field, $this->flatten($objects));
        foreach ($field['speeds'] as $index => $speed) {
            if ($speed < 0) {
                $field['speeds'][$index] = self::DEFAULT_SPEED;
            }
        }

        return $field;
    }

    /**
     * Проходима ли клетка.
     *
     * @param array{width: int, height: int, cell: int, columns: int, rows: int, speeds: array<int, int>} $field Поле.
     * @param int $x Колонка.
     * @param int $y Строка.
     *
     * @return bool Флаг.
     */
    public function passable(array $field, int $x, int $y): bool
    {
        if ($x < 0 || $y < 0 || $x >= $field['columns'] || $y >= $field['rows']) {
            return false;
        }

        return $field['speeds'][$y * $field['columns'] + $x] > 0;
    }

    /**
     * Клетка точки.
     *
     * @param array{width: int, height: int, cell: int, columns: int, rows: int, speeds: array<int, int>} $field Поле.
     * @param array{0: int, 1: int} $point Точка.
     *
     * @return array{0: int, 1: int} Клетка.
     */
    public function cellOf(array $field, array $point): array
    {
        $x = (int) floor($point[0] / $field['cell']);
        $y = (int) floor($point[1] / $field['cell']);

        return [
            max(0, min($field['columns'] - 1, $x)),
            max(0, min($field['rows'] - 1, $y)),
        ];
    }

    /**
     * Центр клетки.
     *
     * @param array{width: int, height: int, cell: int, columns: int, rows: int, speeds: array<int, int>} $field Поле.
     * @param int $x Колонка.
     * @param int $y Строка.
     *
     * @return array{0: int, 1: int} Точка.
     */
    public function cellCenter(array $field, int $x, int $y): array
    {
        return $this->clampPoint($field, [
            (int) round(($x + 0.5) * $field['cell']),
            (int) round(($y + 0.5) * $field['cell']),
        ]);
    }

    /**
     * Прижимает точку к миру.
     *
     * @param array{width: int, height: int, cell: int, columns: int, rows: int, speeds: array<int, int>} $field Поле.
     * @param array{0: int, 1: int} $point Точка.
     *
     * @return array{0: int, 1: int} Точка.
     */
    public function clampPoint(array $field, array $point): array
    {
        return [
            max(0, min($field['width'], (int) round($point[0]))),
            max(0, min($field['height'], (int) round($point[1]))),
        ];
    }

    /**
     * Разворачивает контуры с ручками Безье в полилинии.
     *
     * @param array<int, Contour> $contours Контуры.
     *
     * @return array<int, Area> Полилинии.
     */
    private function flatten(array $contours): array
    {
        $flat = [];
        foreach ($contours as $contour) {
            $flat[] = [
                'id' => $contour['id'],
                'path' => $this->geometry->flattenContour($contour['path'], $contour['handles']),
                'speed' => $contour['speed'],
                'sortOrder' => $contour['sortOrder'],
            ];
        }

        return $flat;
    }

    /**
     * Размер клетки.
     *
     * @param int $width Ширина.
     * @param int $height Высота.
     *
     * @return int Пиксели.
     */
    private function cellSize(int $width, int $height): int
    {
        $raw = (int) ceil(sqrt(($width * $height) / self::TARGET_CELLS));

        return max(2, min(1000, $raw));
    }

    /**
     * Полигоны слоя: поверхности или объекты.
     *
     * @param Field $field Поле.
     * @param array<int, Area> $contours Контуры слоя.
     *
     * @return void
     */
    private function paintContours(array &$field, array $contours): void
    {
        usort(
            $contours,
            static fn (array $left, array $right): int => $left['sortOrder'] <=> $right['sortOrder']
                ?: $left['id'] <=> $right['id'],
        );
        foreach ($contours as $contour) {
            if (count($contour['path']) < 3) {
                continue;
            }

            $this->paintPolygon($field, $contour['path'], $contour['speed']);
        }
    }

    /**
     * Ленты.
     *
     * @param Field $field Поле.
     * @param array<int, Ribbon> $ribbons Ленты.
     *
     * @return void
     */
    private function paintRibbons(array &$field, array $ribbons): void
    {
        usort(
            $ribbons,
            static fn (array $left, array $right): int => $left['sortOrder'] <=> $right['sortOrder']
                ?: $left['id'] <=> $right['id'],
        );
        foreach ($ribbons as $ribbon) {
            if (count($ribbon['points']) < 2 || count($ribbon['widths']) !== count($ribbon['points'])) {
                continue;
            }

            for ($index = 1; $index < count($ribbon['points']); $index += 1) {
                [$points, $widths] = $this->geometry->ribbonSegment(
                    $ribbon['points'],
                    $ribbon['handles'],
                    $ribbon['widths'],
                    $index,
                );
                $speed = $ribbon['segmentSpeeds'][$index - 1] ?? $ribbon['defaultSpeed'];
                $this->paintRibbonSegment($field, $points, $widths, $speed);
            }
        }
    }

    /**
     * Полигон поверхности или объекта.
     *
     * @param Field $field Поле.
     * @param array<int, array{0: int, 1: int}> $polygon Полигон.
     * @param int $speed Скорость.
     *
     * @return void
     */
    private function paintPolygon(array &$field, array $polygon, int $speed): void
    {
        $bounds = $this->cellBounds($field, $polygon);
        for ($y = $bounds['top']; $y <= $bounds['bottom']; $y += 1) {
            for ($x = $bounds['left']; $x <= $bounds['right']; $x += 1) {
                if ($this->cellTouchesPolygon($field['cell'], $x, $y, $polygon)) {
                    $field['speeds'][$y * $field['columns'] + $x] = max(0, min(MapSpeed::MAX, $speed));
                }
            }
        }
    }

    /**
     * Полоса ленты.
     *
     * @param Field $field Поле.
     * @param array<int, array{0: int, 1: int}> $points Осевая линия сегмента.
     * @param array<int, int> $widths Ширины.
     * @param int $speed Скорость.
     *
     * @return void
     */
    private function paintRibbonSegment(array &$field, array $points, array $widths, int $speed): void
    {
        for ($index = 1; $index < count($points); $index += 1) {
            $half = max(1.0, max($widths[$index - 1] ?? 1, $widths[$index] ?? 1) / 2);
            $half = max($half, $field['cell'] * 0.6);
            $this->paintRibbonStroke(
                $field,
                $points[$index - 1],
                $points[$index],
                $half,
                max(0, min(MapSpeed::MAX, $speed)),
            );
        }
    }

    /**
     * Один отрезок ленты.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $start Начало.
     * @param array{0: int, 1: int} $end Конец.
     * @param float $half Полуширина.
     * @param int $speed Скорость.
     *
     * @return void
     */
    private function paintRibbonStroke(array &$field, array $start, array $end, float $half, int $speed): void
    {
        $left = $this->cellOf($field, [min($start[0], $end[0]) - (int) ceil($half), 0]);
        $top = $this->cellOf($field, [0, min($start[1], $end[1]) - (int) ceil($half)]);
        $right = $this->cellOf($field, [max($start[0], $end[0]) + (int) ceil($half), 0]);
        $bottom = $this->cellOf($field, [0, max($start[1], $end[1]) + (int) ceil($half)]);
        for ($y = $top[1]; $y <= $bottom[1]; $y += 1) {
            for ($x = $left[0]; $x <= $right[0]; $x += 1) {
                $center = $this->cellCenter($field, $x, $y);
                if ($this->geometry->pointToSegment($center, $start, $end) <= $half) {
                    $field['speeds'][$y * $field['columns'] + $x] = $speed;
                }
            }
        }
    }

    /**
     * Прямоугольник клеток под полигоном.
     *
     * @param Field $field Поле.
     * @param array<int, array{0: int, 1: int}> $polygon Полигон.
     *
     * @return array{left: int, top: int, right: int, bottom: int} Границы.
     */
    private function cellBounds(array $field, array $polygon): array
    {
        $xs = array_column($polygon, 0);
        $ys = array_column($polygon, 1);
        $left = $this->cellOf($field, [min($xs), 0]);
        $right = $this->cellOf($field, [max($xs), 0]);
        $top = $this->cellOf($field, [0, min($ys)]);
        $bottom = $this->cellOf($field, [0, max($ys)]);

        return ['left' => $left[0], 'top' => $top[1], 'right' => $right[0], 'bottom' => $bottom[1]];
    }

    /**
     * Клетка касается полигона: центр, углы или вершина внутри.
     *
     * @param int $cell Размер клетки.
     * @param int $x Колонка.
     * @param int $y Строка.
     * @param array<int, array{0: int, 1: int}> $polygon Полигон.
     *
     * @return bool Флаг.
     */
    private function cellTouchesPolygon(int $cell, int $x, int $y, array $polygon): bool
    {
        $x0 = $x * $cell;
        $y0 = $y * $cell;
        $x1 = $x0 + $cell;
        $y1 = $y0 + $cell;
        foreach ($this->samples($cell, $x0, $y0) as $sample) {
            if ($this->geometry->pointInPolygon([(int) round($sample[0]), (int) round($sample[1])], $polygon)) {
                return true;
            }
        }

        foreach ($polygon as $point) {
            if ($this->pointInCell($x0, $y0, $x1, $y1, $point)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Вершина полигона внутри клетки.
     *
     * @param int $x0 Левый край.
     * @param int $y0 Верхний край.
     * @param int $x1 Правый край.
     * @param int $y1 Нижний край.
     * @param array{0: int, 1: int} $point Вершина.
     *
     * @return bool Флаг.
     */
    private function pointInCell(int $x0, int $y0, int $x1, int $y1, array $point): bool
    {
        return $point[0] >= $x0 && $point[0] <= $x1 && $point[1] >= $y0 && $point[1] <= $y1;
    }

    /**
     * Точки проверки клетки: центр и углы.
     *
     * @param int $cell Размер клетки.
     * @param int $x0 Левый край.
     * @param int $y0 Верхний край.
     *
     * @return array<int, array{0: float, 1: float}> Точки.
     */
    private function samples(int $cell, int $x0, int $y0): array
    {
        return [
            [$x0 + $cell / 2, $y0 + $cell / 2],
            [$x0, $y0],
            [$x0 + $cell, $y0],
            [$x0 + $cell, $y0 + $cell],
            [$x0, $y0 + $cell],
        ];
    }
}
