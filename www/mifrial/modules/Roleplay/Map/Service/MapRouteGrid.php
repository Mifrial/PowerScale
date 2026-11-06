<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapNoRouteException;
use SplPriorityQueue;

/**
 * Растровая проходимость варианта и A* по ней.
 *
 * Слой поверхности задаёт скорость, лента перекрывает поверхность,
 * объект сверху пишет свою скорость. Ноль блокирует, земля без разметки проходима.
 *
 * @phpstan-type Field array{width: int, height: int, cell: int, columns: int, rows: int, speeds: array<int, int>}
 * @phpstan-type Surface array{id: int, path: array<int, array{0: int, 1: int}>, speed: int, sortOrder: int}
 * @phpstan-type Ribbon array{id: int, points: array<int, array{0: int, 1: int}>, widths: array<int, int>, handles: array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}>, segmentSpeeds: array<int, int>, defaultSpeed: int, sortOrder: int}
 * @phpstan-type RouteObject array{id: int, path: array<int, array{0: int, 1: int}>, speed: int, sortOrder: int}
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
     * @param array<int, Surface> $surfaces Поверхности.
     * @param array<int, Ribbon> $ribbons Ленты.
     * @param array<int, RouteObject> $objects Объекты.
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

        $this->paintSurfaces($field, $surfaces);
        $this->paintRibbons($field, $ribbons);
        $this->paintObjects($field, $objects);
        foreach ($field['speeds'] as $index => $speed) {
            if ($speed < 0) {
                $field['speeds'][$index] = self::DEFAULT_SPEED;
            }
        }

        return $field;
    }

    /**
     * Строит маршрут.
     *
     * @param array{width: int, height: int, cell: int, columns: int, rows: int, speeds: array<int, int>} $field Поле.
     * @param array{0: int, 1: int} $from Начало.
     * @param array{0: int, 1: int} $to Конец.
     *
     * @return array{points: array<int, array{0: int, 1: int}>, distancePixels: float, costPixels: float} Маршрут.
     *
     * @throws MapNoRouteException Если пути нет.
     */
    public function route(array $field, array $from, array $to): array
    {
        $start = $this->cellOf($field, $from);
        $goal = $this->cellOf($field, $to);
        $startPassable = $this->passable($field, $start[0], $start[1]);
        $goalPassable = $this->passable($field, $goal[0], $goal[1]);
        $start = $startPassable ? $start : $this->nearestPassable($field, $start[0], $start[1]);
        $goal = $goalPassable ? $goal : $this->nearestPassable($field, $goal[0], $goal[1]);
        if ($start === null || $goal === null) {
            throw new MapNoRouteException();
        }

        $cells = $this->aStar($field, $start, $goal);
        if ($cells === []) {
            throw new MapNoRouteException();
        }

        $points = [];
        foreach ($cells as $cellPoint) {
            $points[] = $this->cellCenter($field, $cellPoint[0], $cellPoint[1]);
        }

        if (count($points) === 1) {
            $points = [
                $startPassable ? $this->clampPoint($field, $from) : $points[0],
                $goalPassable ? $this->clampPoint($field, $to) : $points[0],
            ];
        } else {
            if ($startPassable) {
                $points[0] = $this->clampPoint($field, $from);
            }

            if ($goalPassable) {
                $points[count($points) - 1] = $this->clampPoint($field, $to);
            }
        }

        $points = $this->dedupe($points);

        return [
            'points' => $points,
            'distancePixels' => $this->length($points),
            'costPixels' => $this->lastCost,
        ];
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
     * Поверхности.
     *
     * @param Field $field Поле.
     * @param array<int, Surface> $surfaces Поверхности.
     *
     * @return void
     */
    private function paintSurfaces(array &$field, array $surfaces): void
    {
        usort(
            $surfaces,
            static fn (array $left, array $right): int => $left['sortOrder'] <=> $right['sortOrder']
                ?: $left['id'] <=> $right['id'],
        );
        foreach ($surfaces as $surface) {
            if (count($surface['path']) < 3) {
                continue;
            }

            $this->paintPolygon($field, $surface['path'], $surface['speed']);
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
     * Объекты.
     *
     * @param Field $field Поле.
     * @param array<int, RouteObject> $objects Объекты.
     *
     * @return void
     */
    private function paintObjects(array &$field, array $objects): void
    {
        usort(
            $objects,
            static fn (array $left, array $right): int => $left['sortOrder'] <=> $right['sortOrder']
                ?: $left['id'] <=> $right['id'],
        );
        foreach ($objects as $object) {
            if (count($object['path']) < 3) {
                continue;
            }

            $this->paintPolygon($field, $object['path'], $object['speed']);
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
        $samples = [
            [$x0 + $cell / 2, $y0 + $cell / 2],
            [$x0, $y0],
            [$x1, $y0],
            [$x1, $y1],
            [$x0, $y1],
        ];
        foreach ($samples as $sample) {
            if ($this->geometry->pointInPolygon([(int) round($sample[0]), (int) round($sample[1])], $polygon)) {
                return true;
            }
        }

        foreach ($polygon as $point) {
            if ($point[0] >= $x0 && $point[0] <= $x1 && $point[1] >= $y0 && $point[1] <= $y1) {
                return true;
            }
        }

        return false;
    }

    /**
     * A*.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $start Начало.
     * @param array{0: int, 1: int} $goal Конец.
     *
     * @return array<int, array{0: int, 1: int}> Клетки пути.
     */
    private function aStar(array $field, array $start, array $goal): array
    {
        $columns = $field['columns'];
        $startKey = $start[1] * $columns + $start[0];
        $goalKey = $goal[1] * $columns + $goal[0];
        $costs = [$startKey => 0.0];
        $previous = [];
        $closed = [];
        $this->lastCost = 0.0;
        $queue = new SplPriorityQueue();
        $queue->insert($startKey, 0.0);
        while (!$queue->isEmpty()) {
            $key = (int) $queue->extract();
            if (isset($closed[$key])) {
                continue;
            }

            $closed[$key] = true;
            if ($key === $goalKey) {
                $this->lastCost = $costs[$key];

                return $this->reconstruct($startKey, $goalKey, $previous, $columns);
            }

            $x = $key % $columns;
            $y = intdiv($key, $columns);
            foreach ($this->neighbours() as $delta) {
                $nextX = $x + $delta[0];
                $nextY = $y + $delta[1];
                if ($nextX < 0 || $nextY < 0 || $nextX >= $columns || $nextY >= $field['rows']) {
                    continue;
                }

                $speed = $field['speeds'][$nextY * $columns + $nextX];
                if ($speed <= 0) {
                    continue;
                }

                if ($delta[0] !== 0 && $delta[1] !== 0) {
                    if (!$this->passable($field, $x + $delta[0], $y) || !$this->passable($field, $x, $y + $delta[1])) {
                        continue;
                    }
                }

                $step = $field['cell'] * ($delta[0] !== 0 && $delta[1] !== 0 ? M_SQRT2 : 1.0);
                $tentative = $costs[$key] + $step * MapSpeed::COST_FACTOR / $speed;
                $nextKey = $nextY * $columns + $nextX;
                if ($tentative >= ($costs[$nextKey] ?? INF)) {
                    continue;
                }

                $costs[$nextKey] = $tentative;
                $previous[$nextKey] = $key;
                $queue->insert($nextKey, -($tentative + $this->heuristic($nextX, $nextY, $goal, $field['cell'])));
            }
        }

        return [];
    }

    /**
     * Восстанавливает путь.
     *
     * @param int $startKey Начало.
     * @param int $goalKey Конец.
     * @param array<int, int> $previous Предыдущие.
     * @param int $columns Колонки.
     *
     * @return array<int, array{0: int, 1: int}> Клетки.
     */
    private function reconstruct(int $startKey, int $goalKey, array $previous, int $columns): array
    {
        $cells = [];
        $key = $goalKey;
        while ($key !== $startKey) {
            $cells[] = [$key % $columns, intdiv($key, $columns)];
            $key = $previous[$key] ?? $startKey;
        }
        $cells[] = [$startKey % $columns, intdiv($startKey, $columns)];

        return array_reverse($cells);
    }

    /**
     * Соседи.
     *
     * @return array<int, array{0: int, 1: int}> Смещения.
     */
    private function neighbours(): array
    {
        return [
            [-1, 0],
            [1, 0],
            [0, -1],
            [0, 1],
            [-1, -1],
            [1, -1],
            [-1, 1],
            [1, 1],
        ];
    }

    /**
     * Эвристика.
     *
     * @param int $x Колонка.
     * @param int $y Строка.
     * @param array{0: int, 1: int} $goal Конец.
     * @param int $cell Клетка.
     *
     * @return float Пиксели.
     */
    private function heuristic(int $x, int $y, array $goal, int $cell): float
    {
        return hypot(($goal[0] - $x) * $cell, ($goal[1] - $y) * $cell);
    }

    /**
     * Проходима ли клетка.
     *
     * @param Field $field Поле.
     * @param int $x Колонка.
     * @param int $y Строка.
     *
     * @return bool Флаг.
     */
    private function passable(array $field, int $x, int $y): bool
    {
        if ($x < 0 || $y < 0 || $x >= $field['columns'] || $y >= $field['rows']) {
            return false;
        }

        return $field['speeds'][$y * $field['columns'] + $x] > 0;
    }

    /**
     * Ближайшая проходимая клетка.
     *
     * @param Field $field Поле.
     * @param int $x Колонка.
     * @param int $y Строка.
     *
     * @return array{0: int, 1: int}|null Клетка или null.
     */
    private function nearestPassable(array $field, int $x, int $y): ?array
    {
        for ($radius = 1; $radius <= 8; $radius += 1) {
            for ($dy = -$radius; $dy <= $radius; $dy += 1) {
                for ($dx = -$radius; $dx <= $radius; $dx += 1) {
                    if (abs($dx) !== $radius && abs($dy) !== $radius) {
                        continue;
                    }

                    if ($this->passable($field, $x + $dx, $y + $dy)) {
                        return [$x + $dx, $y + $dy];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Клетка точки.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $point Точка.
     *
     * @return array{0: int, 1: int} Клетка.
     */
    private function cellOf(array $field, array $point): array
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
     * @param Field $field Поле.
     * @param int $x Колонка.
     * @param int $y Строка.
     *
     * @return array{0: int, 1: int} Точка.
     */
    private function cellCenter(array $field, int $x, int $y): array
    {
        return $this->clampPoint($field, [
            (int) round(($x + 0.5) * $field['cell']),
            (int) round(($y + 0.5) * $field['cell']),
        ]);
    }

    /**
     * Прижимает точку к миру.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $point Точка.
     *
     * @return array{0: int, 1: int} Точка.
     */
    private function clampPoint(array $field, array $point): array
    {
        return [
            max(0, min($field['width'], (int) round($point[0]))),
            max(0, min($field['height'], (int) round($point[1]))),
        ];
    }

    /**
     * Убирает повторы подряд.
     *
     * @param array<int, array{0: int, 1: int}> $points Точки.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    private function dedupe(array $points): array
    {
        $unique = [];
        foreach ($points as $point) {
            $last = $unique[count($unique) - 1] ?? null;
            if ($last !== null && $last[0] === $point[0] && $last[1] === $point[1]) {
                continue;
            }

            $unique[] = $point;
        }

        return $unique;
    }

    /**
     * Длина ломаной.
     *
     * @param array<int, array{0: int, 1: int}> $points Точки.
     *
     * @return float Пиксели.
     */
    private function length(array $points): float
    {
        $length = 0.0;
        for ($index = 1; $index < count($points); $index += 1) {
            $length += hypot($points[$index][0] - $points[$index - 1][0], $points[$index][1] - $points[$index - 1][1]);
        }

        return $length;
    }

    /**
     * Стоимость последнего маршрута.
     */
    private float $lastCost = 0.0;
}
