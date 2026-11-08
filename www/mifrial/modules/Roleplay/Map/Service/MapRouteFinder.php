<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapNoRouteException;

/**
 * Маршрут по полю скоростей: концы, поиск, точки и стоимость.
 *
 * @phpstan-import-type Field from MapRouteGrid
 */
final class MapRouteFinder
{
    private readonly MapRouteSearch $search;

    /**
     * Создаёт поиск маршрута.
     *
     * @param MapRouteGrid $grid Сетка поля.
     *
     * @return void
     */
    public function __construct(
        private readonly MapRouteGrid $grid,
    ) {
        $this->search = new MapRouteSearch($grid);
    }

    /**
     * Строит маршрут.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $from Начало.
     * @param array{0: int, 1: int} $to Конец.
     *
     * @return array{points: array<int, array{0: int, 1: int}>, distancePixels: float, costPixels: float} Маршрут.
     *
     * @throws MapNoRouteException Если пути нет.
     */
    public function route(array $field, array $from, array $to): array
    {
        [$start, $goal, $startPassable, $goalPassable] = $this->endpoints($field, $from, $to);
        $found = $this->search->search($field, $start, $goal);
        if ($found === null) {
            throw new MapNoRouteException();
        }

        $points = $this->clampEnds(
            $this->centers($field, $found['cells']),
            $field,
            $from,
            $to,
            $startPassable,
            $goalPassable,
        );

        return [
            'points' => $points,
            'distancePixels' => $this->length($points),
            'costPixels' => $found['cost'],
        ];
    }

    /**
     * Проходимые концы маршрута.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $from Начало.
     * @param array{0: int, 1: int} $to Конец.
     *
     * @return array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}, 2: bool, 3: bool} Клетки и их проходимость.
     *
     * @throws MapNoRouteException Если рядом с концом нет проходимой клетки.
     */
    private function endpoints(array $field, array $from, array $to): array
    {
        $start = $this->grid->cellOf($field, $from);
        $goal = $this->grid->cellOf($field, $to);
        $startPassable = $this->grid->passable($field, $start[0], $start[1]);
        $goalPassable = $this->grid->passable($field, $goal[0], $goal[1]);
        $start = $startPassable ? $start : $this->nearestPassable($field, $start[0], $start[1]);
        $goal = $goalPassable ? $goal : $this->nearestPassable($field, $goal[0], $goal[1]);
        if ($start === null || $goal === null) {
            throw new MapNoRouteException();
        }

        return [$start, $goal, $startPassable, $goalPassable];
    }

    /**
     * Центры клеток пути.
     *
     * @param Field $field Поле.
     * @param array<int, array{0: int, 1: int}> $cells Клетки.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    private function centers(array $field, array $cells): array
    {
        $points = [];
        foreach ($cells as $cell) {
            $points[] = $this->grid->cellCenter($field, $cell[0], $cell[1]);
        }

        return $points;
    }

    /**
     * Ставит концы на точные координаты и убирает повторы.
     *
     * @param array<int, array{0: int, 1: int}> $points Точки пути.
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $from Начало.
     * @param array{0: int, 1: int} $to Конец.
     * @param bool $startPassable Начало проходимо.
     * @param bool $goalPassable Конец проходим.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    private function clampEnds(
        array $points,
        array $field,
        array $from,
        array $to,
        bool $startPassable,
        bool $goalPassable,
    ): array {
        if (count($points) === 1) {
            return $this->dedupe([
                $startPassable ? $this->grid->clampPoint($field, $from) : $points[0],
                $goalPassable ? $this->grid->clampPoint($field, $to) : $points[0],
            ]);
        }

        if ($startPassable) {
            $points[0] = $this->grid->clampPoint($field, $from);
        }

        if ($goalPassable) {
            $points[count($points) - 1] = $this->grid->clampPoint($field, $to);
        }

        return $this->dedupe($points);
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
            foreach ($this->ring($x, $y, $radius) as $cell) {
                if ($this->grid->passable($field, $cell[0], $cell[1])) {
                    return $cell;
                }
            }
        }

        return null;
    }

    /**
     * Клетки кольца радиуса вокруг точки.
     *
     * @param int $x Колонка.
     * @param int $y Строка.
     * @param int $radius Радиус.
     *
     * @return array<int, array{0: int, 1: int}> Клетки.
     */
    private function ring(int $x, int $y, int $radius): array
    {
        $cells = [];
        for ($dy = -$radius; $dy <= $radius; $dy += 1) {
            for ($dx = -$radius; $dx <= $radius; $dx += 1) {
                if (abs($dx) !== $radius && abs($dy) !== $radius) {
                    continue;
                }

                $cells[] = [$x + $dx, $y + $dy];
            }
        }

        return $cells;
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
}
