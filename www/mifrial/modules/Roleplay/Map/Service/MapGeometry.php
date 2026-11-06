<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

/**
 * Плоская геометрия карты: пересечения и расстояния.
 *
 * Класс без состояния и без зависимостей: проверки предупреждений о блокерах.
 */
final class MapGeometry
{
    /**
     * Пересекаются ли два отрезка.
     *
     * @param array{0: int, 1: int} $a Первая точка первого.
     * @param array{0: int, 1: int} $b Вторая точка первого.
     * @param array{0: int, 1: int} $c Первая точка второго.
     * @param array{0: int, 1: int} $d Вторая точка второго.
     *
     * @return bool Флаг.
     */
    public function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $d1 = $this->cross($c, $d, $a);
        $d2 = $this->cross($c, $d, $b);
        $d3 = $this->cross($a, $b, $c);
        $d4 = $this->cross($a, $b, $d);

        if ($this->straddles($d1, $d2, $d3, $d4)) {
            return true;
        }

        return $this->endpointTouches($d1, $d2, $d3, $d4, $a, $b, $c, $d);
    }

    /**
     * Расстояние между отрезками.
     *
     * @param array{0: int, 1: int} $a Первая точка первого.
     * @param array{0: int, 1: int} $b Вторая точка первого.
     * @param array{0: int, 1: int} $c Первая точка второго.
     * @param array{0: int, 1: int} $d Вторая точка второго.
     *
     * @return float Пиксели.
     */
    public function segmentDistance(array $a, array $b, array $c, array $d): float
    {
        if ($this->segmentsIntersect($a, $b, $c, $d)) {
            return 0.0;
        }

        return min(
            $this->pointToSegment($a, $c, $d),
            $this->pointToSegment($b, $c, $d),
            $this->pointToSegment($c, $a, $b),
            $this->pointToSegment($d, $a, $b),
        );
    }

    /**
     * Пересекает ли полилиния полигон.
     *
     * @param array<int, array{0: int, 1: int}> $line Полилиния.
     * @param array<int, array{0: int, 1: int}> $polygon Полигон.
     *
     * @return bool Флаг.
     */
    public function polylineIntersectsPolygon(array $line, array $polygon): bool
    {
        $hit = count($line) > 0 && count($polygon) >= 3 && $this->pointInPolygon($line[0], $polygon);
        for ($index = 1; !$hit && $index < count($line); $index += 1) {
            $hit = $this->segmentCrossesPolygon($line[$index - 1], $line[$index], $polygon);
        }

        return $hit;
    }

    /**
     * Пересекает ли полилиния ленту (осевая линия с шириной).
     *
     * @param array<int, array{0: int, 1: int}> $line Полилиния.
     * @param array<int, array{0: int, 1: int}> $points Осевая линия ленты.
     * @param array<int, int> $widths Ширина по точкам.
     *
     * @return bool Флаг.
     */
    public function polylineIntersectsRibbon(array $line, array $points, array $widths): bool
    {
        if (count($line) === 0 || count($points) < 2 || count($widths) !== count($points)) {
            return false;
        }

        for ($index = 1; $index < count($line); $index += 1) {
            if ($this->segmentCrossesRibbon($line[$index - 1], $line[$index], $points, $widths)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Разворачивает ленту с ручками Безье в полилинию с ширинами.
     *
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки.
     * @param array<int, int> $widths Ширина по точкам.
     * @param int $samples Число сэмплов на сегмент.
     *
     * @return array{0: array<int, array{0: int, 1: int}>, 1: array<int, int>} Точки и ширины.
     */
    public function flattenRibbon(array $points, array $handles, array $widths, int $samples = 16): array
    {
        if (count($points) < 2 || count($widths) !== count($points)) {
            return [$points, $widths];
        }

        if ($handles === []) {
            $handles = array_fill(0, count($points), ['in' => [0, 0], 'out' => [0, 0]]);
        }

        $flatPoints = [];
        $flatWidths = [];
        $steps = max(1, $samples);
        for ($index = 1; $index < count($points); $index += 1) {
            [$segmentPoints, $segmentWidths] = $this->flattenSegment($points, $handles, $widths, $index, $steps);
            $flatPoints = array_merge($flatPoints, $segmentPoints);
            $flatWidths = array_merge($flatWidths, $segmentWidths);
        }

        return [$flatPoints, $flatWidths];
    }

    /**
     * Разворачивает замкнутый контур области с ручками Безье в полилинию.
     *
     * Сегмент `i → i+1` использует `out` вершины `i` и `in` вершины `i+1`;
     * последний сегмент замыкается с `out` последней вершины на `in` первой.
     * Повторной первой точки в результате нет.
     *
     * @param array<int, array{0: int, 1: int}> $path Вершины контура.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки.
     * @param int $samples Число сэмплов на сегмент.
     *
     * @return array<int, array{0: int, 1: int}> Полилиния.
     */
    public function flattenContour(array $path, array $handles, int $samples = 12): array
    {
        $count = count($path);
        if ($count < 3) {
            return $path;
        }

        if (!$this->hasCurveHandles($handles, $count)) {
            return $path;
        }

        $steps = max(1, $samples);
        $flat = [];
        for ($index = 0; $index < $count; $index += 1) {
            $next = ($index + 1) % $count;
            $start = $path[$index];
            $end = $path[$next];
            $startOut = $handles[$index]['out'];
            $endIn = $handles[$next]['in'];
            $controlOne = [$start[0] + $startOut[0], $start[1] + $startOut[1]];
            $controlTwo = [$end[0] + $endIn[0], $end[1] + $endIn[1]];
            for ($step = 0; $step < $steps; $step += 1) {
                $flat[] = $this->cubicPoint($start, $controlOne, $controlTwo, $end, $step / $steps);
            }
        }

        return $flat;
    }

    /**
     * Есть ли в контуре хоть одна ненулевая ручка.
     *
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки.
     * @param int $count Число вершин.
     *
     * @return bool Флаг.
     */
    private function hasCurveHandles(array $handles, int $count): bool
    {
        if ($count < 1) {
            return false;
        }

        foreach ($handles as $handle) {
            $in = $handle['in'];
            $out = $handle['out'];
            if (abs($in[0]) + abs($in[1]) + abs($out[0]) + abs($out[1]) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Разворачивает один сегмент ленты.
     *
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки.
     * @param array<int, int> $widths Ширина по точкам.
     * @param int $index Номер сегмента (1..count-1).
     * @param int $samples Число сэмплов.
     *
     * @return array{0: array<int, array{0: int, 1: int}>, 1: array<int, int>} Точки и ширины.
     */
    public function ribbonSegment(
        array $points,
        array $handles,
        array $widths,
        int $index,
        int $samples = 8,
    ): array {
        if ($index < 1 || $index >= count($points) || count($widths) !== count($points)) {
            return [[], []];
        }

        if ($handles === []) {
            $handles = array_fill(0, count($points), ['in' => [0, 0], 'out' => [0, 0]]);
        }

        return $this->flattenSegment($points, $handles, $widths, $index, max(1, $samples));
    }

    /**
     * Точка внутри полигона.
     *
     * @param array{0: int, 1: int} $point Точка.
     * @param array<int, array{0: int, 1: int}> $polygon Полигон.
     *
     * @return bool Флаг.
     */
    public function pointInPolygon(array $point, array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $count = count($polygon);
        for ($index = 0, $previous = $count - 1; $index < $count; $previous = $index, $index += 1) {
            if ($this->edgeCrossesRay($point, $polygon[$previous], $polygon[$index])) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Расстояние от точки до отрезка.
     *
     * @param array{0: int, 1: int} $point Точка.
     * @param array{0: int, 1: int} $start Начало.
     * @param array{0: int, 1: int} $end Конец.
     *
     * @return float Пиксели.
     */
    public function pointToSegment(array $point, array $start, array $end): float
    {
        $segmentX = $end[0] - $start[0];
        $segmentY = $end[1] - $start[1];
        $lengthSquared = $segmentX * $segmentX + $segmentY * $segmentY;
        if ($lengthSquared === 0) {
            return hypot($point[0] - $start[0], $point[1] - $start[1]);
        }

        $projection = (($point[0] - $start[0]) * $segmentX + ($point[1] - $start[1]) * $segmentY)
            / $lengthSquared;
        $clamped = min(1.0, max(0.0, $projection));

        return hypot(
            $point[0] - ($start[0] + $segmentX * $clamped),
            $point[1] - ($start[1] + $segmentY * $clamped),
        );
    }

    /**
     * Сэмплы одного сегмента ленты.
     *
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки.
     * @param array<int, int> $widths Ширина по точкам.
     * @param int $index Номер сегмента.
     * @param int $steps Число шагов.
     *
     * @return array{0: array<int, array{0: int, 1: int}>, 1: array<int, int>} Точки и ширины.
     */
    private function flattenSegment(array $points, array $handles, array $widths, int $index, int $steps): array
    {
        $start = $points[$index - 1];
        $end = $points[$index];
        $startHandle = $handles[$index - 1]['out'] ?? [0, 0];
        $endHandle = $handles[$index]['in'] ?? [0, 0];
        $controlOne = [$start[0] + $startHandle[0], $start[1] + $startHandle[1]];
        $controlTwo = [$end[0] + $endHandle[0], $end[1] + $endHandle[1]];
        $startWidth = $widths[$index - 1];
        $endWidth = $widths[$index];
        $segmentPoints = [];
        $segmentWidths = [];
        for ($step = $index === 1 ? 0 : 1; $step <= $steps; $step += 1) {
            $t = $step / $steps;
            $segmentPoints[] = $this->cubicPoint($start, $controlOne, $controlTwo, $end, $t);
            $segmentWidths[] = (int) round($startWidth + ($endWidth - $startWidth) * $t);
        }

        return [$segmentPoints, $segmentWidths];
    }

    /**
     * Точка кубической кривой Безье.
     *
     * @param array{0: int, 1: int} $start Начало.
     * @param array{0: int, 1: int} $controlOne Первая ручка.
     * @param array{0: int, 1: int} $controlTwo Вторая ручка.
     * @param array{0: int, 1: int} $end Конец.
     * @param float $t Параметр 0..1.
     *
     * @return array{0: int, 1: int} Точка.
     */
    private function cubicPoint(array $start, array $controlOne, array $controlTwo, array $end, float $t): array
    {
        $inverse = 1 - $t;
        $a = $inverse * $inverse * $inverse;
        $b = 3 * $inverse * $inverse * $t;
        $c = 3 * $inverse * $t * $t;
        $d = $t * $t * $t;

        return [
            (int) round($a * $start[0] + $b * $controlOne[0] + $c * $controlTwo[0] + $d * $end[0]),
            (int) round($a * $start[1] + $b * $controlOne[1] + $c * $controlTwo[1] + $d * $end[1]),
        ];
    }

    /**
     * Отрезок пересекает контур полигона.
     *
     * @param array{0: int, 1: int} $start Начало отрезка.
     * @param array{0: int, 1: int} $end Конец отрезка.
     * @param array<int, array{0: int, 1: int}> $polygon Полигон.
     *
     * @return bool Флаг.
     */
    private function segmentCrossesPolygon(array $start, array $end, array $polygon): bool
    {
        $count = count($polygon);
        for ($index = 0; $index < $count; $index += 1) {
            $next = ($index + 1) % $count;
            if ($this->segmentsIntersect($start, $end, $polygon[$index], $polygon[$next])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Отрезок пересекает ленту.
     *
     * @param array{0: int, 1: int} $start Начало отрезка.
     * @param array{0: int, 1: int} $end Конец отрезка.
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, int> $widths Ширина.
     *
     * @return bool Флаг.
     */
    private function segmentCrossesRibbon(array $start, array $end, array $points, array $widths): bool
    {
        for ($index = 1; $index < count($points); $index += 1) {
            $halfWidth = max($widths[$index - 1], $widths[$index]) / 2;
            $distance = $this->segmentDistance($start, $end, $points[$index - 1], $points[$index]);
            if ($distance <= $halfWidth) {
                return true;
            }
        }

        return false;
    }

    /**
     * Концы отрезков лежат по разные стороны.
     *
     * @param int $d1 Ориентация первой точки.
     * @param int $d2 Ориентация второй точки.
     * @param int $d3 Ориентация третьей точки.
     * @param int $d4 Ориентация четвёртой точки.
     *
     * @return bool Флаг.
     */
    private function straddles(int $d1, int $d2, int $d3, int $d4): bool
    {
        return $this->opposite($d1, $d2) && $this->opposite($d3, $d4);
    }

    /**
     * Знаки ориентаций противоположны.
     *
     * @param int $left Первая ориентация.
     * @param int $right Вторая ориентация.
     *
     * @return bool Флаг.
     */
    private function opposite(int $left, int $right): bool
    {
        return ($left > 0 && $right < 0) || ($left < 0 && $right > 0);
    }

    /**
     * Концевая точка лежит на другом отрезке при нулевой ориентации.
     *
     * @param int $d1 Ориентация первой точки.
     * @param int $d2 Ориентация второй точки.
     * @param int $d3 Ориентация третьей точки.
     * @param int $d4 Ориентация четвёртой точки.
     * @param array{0: int, 1: int} $a Первая точка первого.
     * @param array{0: int, 1: int} $b Вторая точка первого.
     * @param array{0: int, 1: int} $c Первая точка второго.
     * @param array{0: int, 1: int} $d Вторая точка второго.
     *
     * @return bool Флаг.
     */
    private function endpointTouches(
        int $d1,
        int $d2,
        int $d3,
        int $d4,
        array $a,
        array $b,
        array $c,
        array $d,
    ): bool {
        $first = ($d1 === 0 && $this->onSegment($c, $d, $a))
            || ($d2 === 0 && $this->onSegment($c, $d, $b));
        $second = ($d3 === 0 && $this->onSegment($a, $b, $c))
            || ($d4 === 0 && $this->onSegment($a, $b, $d));

        return $first || $second;
    }

    /**
     * Луч точки пересекает ребро полигона.
     *
     * @param array{0: int, 1: int} $point Точка.
     * @param array{0: int, 1: int} $previous Предыдущая вершина.
     * @param array{0: int, 1: int} $current Текущая вершина.
     *
     * @return bool Флаг.
     */
    private function edgeCrossesRay(array $point, array $previous, array $current): bool
    {
        if (($current[1] > $point[1]) === ($previous[1] > $point[1])) {
            return false;
        }

        $x = ($previous[0] - $current[0]) * ($point[1] - $current[1])
            / ($previous[1] - $current[1])
            + $current[0];

        return $point[0] < $x;
    }

    /**
     * Косое произведение (c - a) × (b - a).
     *
     * @param array{0: int, 1: int} $a Первая.
     * @param array{0: int, 1: int} $b Вторая.
     * @param array{0: int, 1: int} $c Третья.
     *
     * @return int Знак и величина.
     */
    private function cross(array $a, array $b, array $c): int
    {
        return ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
    }

    /**
     * Лежит ли точка на отрезке при нулевом cross.
     *
     * @param array{0: int, 1: int} $start Начало.
     * @param array{0: int, 1: int} $end Конец.
     * @param array{0: int, 1: int} $point Точка.
     *
     * @return bool Флаг.
     */
    private function onSegment(array $start, array $end, array $point): bool
    {
        return $point[0] <= max($start[0], $end[0])
            && $point[0] >= min($start[0], $end[0])
            && $point[1] <= max($start[1], $end[1])
            && $point[1] >= min($start[1], $end[1]);
    }
}
