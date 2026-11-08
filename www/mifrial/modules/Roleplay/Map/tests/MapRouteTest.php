<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Tests;

use Mifrial\Roleplay\Map\Exception\MapNoRouteException;
use Mifrial\Roleplay\Map\Service\MapGeometry;
use Mifrial\Roleplay\Map\Service\MapRouteFinder;
use Mifrial\Roleplay\Map\Service\MapRouteGrid;
use PHPUnit\Framework\TestCase;

/**
 * Растровая сетка и поиск маршрута.
 */
final class MapRouteTest extends TestCase
{
    private MapRouteGrid $grid;

    private MapRouteFinder $finder;

    /**
     * Свежая сетка и поиск.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->grid = new MapRouteGrid(new MapGeometry());
        $this->finder = new MapRouteFinder($this->grid);
    }

    /**
     * Прямой маршрут по открытой земле.
     *
     * @return void
     */
    public function testOpenGroundIsPassable(): void
    {
        $route = $this->route($this->grid->build(200, 100, [], [], []), [10, 10], [190, 10]);

        self::assertGreaterThan(170, $route['distancePixels']);
        self::assertLessThan(190, $route['distancePixels']);
    }

    /**
     * Вода обходится.
     *
     * @return void
     */
    public function testWaterIsDetoured(): void
    {
        $route = $this->route(
            $this->grid->build(200, 100, [$this->water()], [], []),
            [10, 50],
            [190, 50],
        );

        self::assertGreaterThan(190, $route['distancePixels']);
    }

    /**
     * Дорога поверх воды делает проход коротким.
     *
     * @return void
     */
    public function testRoadRibbonOverridesWater(): void
    {
        $ribbons = [
            [
                'id' => 1,
                'points' => [[0, 50], [200, 50]],
                'widths' => [10, 10],
                'handles' => [],
                'segmentSpeeds' => [],
                'defaultSpeed' => 10000,
                'sortOrder' => 1,
            ],
        ];
        $route = $this->route(
            $this->grid->build(200, 100, [$this->water()], $ribbons, []),
            [10, 50],
            [190, 50],
        );

        self::assertLessThan(190, $route['distancePixels']);
    }

    /**
     * Объект со скоростью открывает блокер.
     *
     * @return void
     */
    public function testPassableObjectOpensBlocker(): void
    {
        $objects = [
            [
                'id' => 1,
                'path' => [[80, 20], [120, 20], [120, 80], [80, 80]],
                'handles' => [],
                'speed' => 10000,
                'sortOrder' => 1,
            ],
        ];
        $route = $this->route(
            $this->grid->build(200, 100, [$this->water()], [], $objects),
            [10, 50],
            [190, 50],
        );

        self::assertLessThan(190, $route['distancePixels']);
    }

    /**
     * Полностью закрытая карта без маршрута.
     *
     * @return void
     */
    public function testBlockingObjectHasNoRoute(): void
    {
        $objects = [
            [
                'id' => 1,
                'path' => [[0, 0], [200, 0], [200, 100], [0, 100]],
                'handles' => [],
                'speed' => 0,
                'sortOrder' => 0,
            ],
        ];
        $field = $this->grid->build(200, 100, [], [], $objects);

        $this->expectException(MapNoRouteException::class);
        $this->finder->route($field, [10, 50], [190, 50]);
    }

    /**
     * Маршрут по полю.
     *
     * @param array $field Поле.
     * @param array{0: int, 1: int} $from Начало.
     * @param array{0: int, 1: int} $to Конец.
     *
     * @return array{points: array<int, array{0: int, 1: int}>, distancePixels: float, costPixels: float} Маршрут.
     */
    private function route(array $field, array $from, array $to): array
    {
        return $this->finder->route($field, $from, $to);
    }

    /**
     * Водный блокер.
     *
     * @return array{id: int, path: array<int, array{0: int, 1: int}>, handles: array, speed: int, sortOrder: int} Блокер.
     */
    private function water(): array
    {
        return [
            'id' => 1,
            'path' => [[80, 20], [120, 20], [120, 80], [80, 80]],
            'handles' => [],
            'speed' => 0,
            'sortOrder' => 0,
        ];
    }
}
