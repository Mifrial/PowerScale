<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

/**
 * Плоская геометрия карты: разворот кривых и попадания.
 *
 * Класс без состояния и без зависимостей.
 */
final class MapGeometry
{
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
}
