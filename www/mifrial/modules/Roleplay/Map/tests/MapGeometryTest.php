<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Tests;

use Mifrial\Roleplay\Map\Service\MapGeometry;
use PHPUnit\Framework\TestCase;

final class MapGeometryTest extends TestCase
{
    private MapGeometry $geometry;

    /**
     * Свежая геометрия.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->geometry = new MapGeometry();
    }

    /**
     * Разворот одного сегмента ленты с ручками Безье.
     *
     * @return void
     */
    public function testRibbonSegmentWithHandles(): void
    {
        $handles = [
            ['in' => [0, 0], 'out' => [0, 100]],
            ['in' => [0, 100], 'out' => [0, 0]],
        ];
        [$points, $widths] = $this->geometry->ribbonSegment([[0, 0], [100, 0]], $handles, [10, 20], 1, 4);

        self::assertGreaterThan(2, count($points));
        self::assertSame([0, 0], $points[0]);
        self::assertSame([100, 0], $points[count($points) - 1]);
        self::assertGreaterThan(0, $points[2][1]);
        self::assertSame(10, $widths[0]);
        self::assertSame(20, $widths[count($widths) - 1]);
    }

    /**
     * Смещение индекса за пределы ленты даёт пустой сегмент.
     *
     * @return void
     */
    public function testRibbonSegmentRejectsUnknownIndex(): void
    {
        self::assertSame([[], []], $this->geometry->ribbonSegment([[0, 0], [100, 0]], [], [10, 20], 0, 4));
        self::assertSame([[], []], $this->geometry->ribbonSegment([[0, 0], [100, 0]], [], [10, 20], 2, 4));
    }

    /**
     * Замкнутый контур: прямые рёбра не сэмплируются, кривые — да.
     *
     * @return void
     */
    public function testFlattenContour(): void
    {
        $square = [[0, 0], [100, 0], [100, 100], [0, 100]];
        self::assertSame($square, $this->geometry->flattenContour($square, [], 4));

        $handles = [
            ['in' => [0, 0], 'out' => [0, 40]],
            ['in' => [0, 40], 'out' => [0, 0]],
            ['in' => [0, 0], 'out' => [0, 0]],
            ['in' => [0, 0], 'out' => [0, 0]],
        ];
        $flat = $this->geometry->flattenContour($square, $handles, 4);

        self::assertCount(16, $flat);
        self::assertSame([0, 0], $flat[0]);
        self::assertGreaterThan(0, $flat[1][1]);
        self::assertSame([100, 0], $flat[4]);
        self::assertSame([100, 100], $flat[8]);
        self::assertSame([0, 100], $flat[12]);
        self::assertTrue($this->geometry->pointInPolygon([50, 60], $flat));
        self::assertFalse($this->geometry->pointInPolygon([50, 5], $flat));
    }

    /**
     * Точка в полигоне и расстояние до отрезка.
     *
     * @return void
     */
    public function testPointHelpers(): void
    {
        $square = [[0, 0], [10, 0], [10, 10], [0, 10]];

        self::assertTrue($this->geometry->pointInPolygon([5, 5], $square));
        self::assertFalse($this->geometry->pointInPolygon([15, 5], $square));
        self::assertSame(5.0, $this->geometry->pointToSegment([5, 5], [0, 0], [10, 0]));
        self::assertSame(5.0, $this->geometry->pointToSegment([-5, 0], [0, 0], [10, 0]));
    }
}
