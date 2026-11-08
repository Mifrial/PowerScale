<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use SplPriorityQueue;

/**
 * Поиск A* по готовому полю скоростей.
 *
 * @phpstan-import-type Field from MapRouteGrid
 */
final class MapRouteSearch
{
    /**
     * Смещения соседей: стороны и диагонали.
     */
    private const DIRECTIONS = [
        [-1, 0],
        [1, 0],
        [0, -1],
        [0, 1],
        [-1, -1],
        [1, -1],
        [-1, 1],
        [1, 1],
    ];

    /**
     * Создаёт поиск.
     *
     * @param MapRouteGrid $grid Сетка поля.
     *
     * @return void
     */
    public function __construct(
        private readonly MapRouteGrid $grid,
    ) {
    }

    /**
     * Ищет клетки кратчайшего пути.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $start Начало.
     * @param array{0: int, 1: int} $goal Конец.
     *
     * @return array{cells: array<int, array{0: int, 1: int}>, cost: float}|null Путь или null.
     */
    public function search(array $field, array $start, array $goal): ?array
    {
        $columns = $field['columns'];
        $startKey = $start[1] * $columns + $start[0];
        $goalKey = $goal[1] * $columns + $goal[0];
        $costs = [$startKey => 0.0];
        $previous = [];
        $closed = [];
        $queue = new SplPriorityQueue();
        $queue->insert($startKey, 0.0);
        while (!$queue->isEmpty()) {
            $key = (int) $queue->extract();
            if (isset($closed[$key])) {
                continue;
            }

            $closed[$key] = true;
            if ($key === $goalKey) {
                return [
                    'cells' => $this->reconstruct($startKey, $goalKey, $previous, $columns),
                    'cost' => $costs[$key],
                ];
            }

            $this->expand($field, $key, $goal, $costs, $previous, $queue);
        }

        return null;
    }

    /**
     * Раскрывает соседей клетки.
     *
     * @param Field $field Поле.
     * @param int $key Клетка.
     * @param array{0: int, 1: int} $goal Конец.
     * @param array<int, float> $costs Стоимости.
     * @param array<int, int> $previous Предыдущие.
     * @param SplPriorityQueue $queue Очередь.
     *
     * @return void
     */
    private function expand(array $field, int $key, array $goal, array &$costs, array &$previous, SplPriorityQueue $queue): void
    {
        $columns = $field['columns'];
        $x = $key % $columns;
        $y = intdiv($key, $columns);
        foreach (self::DIRECTIONS as $delta) {
            $nextKey = $this->step($field, $x, $y, $delta);
            if ($nextKey === null) {
                continue;
            }

            $tentative = $costs[$key] + $this->stepCost($field, $delta, $nextKey);
            if ($tentative >= ($costs[$nextKey] ?? INF)) {
                continue;
            }

            $costs[$nextKey] = $tentative;
            $previous[$nextKey] = $key;
            $queue->insert($nextKey, -($tentative + $this->heuristic($field, $nextKey, $goal)));
        }
    }

    /**
     * Клетка-сосед, если шаг допустим.
     *
     * @param Field $field Поле.
     * @param int $x Колонка.
     * @param int $y Строка.
     * @param array{0: int, 1: int} $delta Смещение.
     *
     * @return int|null Ключ клетки или null.
     */
    private function step(array $field, int $x, int $y, array $delta): ?int
    {
        $nextX = $x + $delta[0];
        $nextY = $y + $delta[1];
        if (!$this->grid->passable($field, $nextX, $nextY)) {
            return null;
        }

        if (
            $delta[0] !== 0 && $delta[1] !== 0
            && (!$this->grid->passable($field, $x + $delta[0], $y) || !$this->grid->passable($field, $x, $y + $delta[1]))
        ) {
            return null;
        }

        return $nextY * $field['columns'] + $nextX;
    }

    /**
     * Стоимость шага в клетку.
     *
     * @param Field $field Поле.
     * @param array{0: int, 1: int} $delta Смещение.
     * @param int $nextKey Клетка.
     *
     * @return float Стоимость.
     */
    private function stepCost(array $field, array $delta, int $nextKey): float
    {
        $step = $field['cell'] * ($delta[0] !== 0 && $delta[1] !== 0 ? M_SQRT2 : 1.0);

        return $step * MapSpeed::COST_FACTOR / $field['speeds'][$nextKey];
    }

    /**
     * Эвристика до конца.
     *
     * @param Field $field Поле.
     * @param int $key Клетка.
     * @param array{0: int, 1: int} $goal Конец.
     *
     * @return float Пиксели.
     */
    private function heuristic(array $field, int $key, array $goal): float
    {
        $x = $key % $field['columns'];
        $y = intdiv($key, $field['columns']);

        return hypot(($goal[0] - $x) * $field['cell'], ($goal[1] - $y) * $field['cell']);
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
}
