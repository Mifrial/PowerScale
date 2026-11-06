<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Tests;

use Mifrial\Roleplay\Map\Exception\MapNoRouteException;
use Mifrial\Roleplay\Map\Service\MapGeometry;
use Mifrial\Roleplay\Map\Service\MapRouteGrid;
use PHPUnit\Framework\TestCase;

/**
 * Сетка проходимости и A*.
 */
final class MapRouteGridTest extends TestCase
{
    /**
     * Прямой маршрут по открытой земле.
     *
     * @return void
     */
    public function testOpenGroundIsPassable(): void
    {
        $route = $this->grid()->route($this->grid()->build(200, 100, [], [], []), [10, 10], [190, 10]);

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
        $surfaces = [
            ['id' => 1, 'path' => [[80, 20], [120, 20], [120, 80], [80, 80]], 'speed' => 0, 'sortOrder' => 0],
        ];
        $route = $this->grid()->route($this->grid()->build(200, 100, $surfaces, [], []), [10, 50], [190, 50]);

        self::assertGreaterThan(190, $route['distancePixels']);
    }

    /**
     * Дорога поверх воды делает проход коротким.
     *
     * @return void
     */
    public function testRoadRibbonOverridesWater(): void
    {
        $surfaces = [
            ['id' => 1, 'path' => [[80, 20], [120, 20], [120, 80], [80, 80]], 'speed' => 0, 'sortOrder' => 0],
        ];
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
        $route = $this->grid()->route($this->grid()->build(200, 100, $surfaces, $ribbons, []), [10, 50], [190, 50]);

        self::assertLessThan(190, $route['distancePixels']);
    }

    /**
     * Объект со скоростью открывает блокер.
     *
     * @return void
     */
    public function testPassableObjectOpensBlocker(): void
    {
        $surfaces = [
            ['id' => 1, 'path' => [[80, 20], [120, 20], [120, 80], [80, 80]], 'speed' => 0, 'sortOrder' => 0],
        ];
        $objects = [
            ['id' => 1, 'path' => [[80, 20], [120, 20], [120, 80], [80, 80]], 'speed' => 10000, 'sortOrder' => 1],
        ];
        $route = $this->grid()->route($this->grid()->build(200, 100, $surfaces, [], $objects), [10, 50], [190, 50]);

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
            ['id' => 1, 'path' => [[0, 0], [200, 0], [200, 100], [0, 100]], 'speed' => 0, 'sortOrder' => 0],
        ];
        $field = $this->grid()->build(200, 100, [], [], $objects);

        $this->expectException(MapNoRouteException::class);
        $this->grid()->route($field, [10, 50], [190, 50]);
    }

    /**
     * Сетка.
     *
     * @return MapRouteGrid Сетка.
     */
    private function grid(): MapRouteGrid
    {
        return new MapRouteGrid(new MapGeometry());
    }
}
