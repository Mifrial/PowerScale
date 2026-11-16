<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Tests;

use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Service\SceneBoardBounds;
use Mifrial\Roleplay\Scene\Service\SceneElementValidator;
use Mifrial\Roleplay\Scene\Service\SceneSaveFields;
use Mifrial\Roleplay\Scene\Service\SceneSaveFileResolver;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет геометрию элементов: полигон и ленту с ручками Безье.
 */
final class SceneElementValidatorTest extends TestCase
{
    /**
     * Полигон с ручками сохраняет их в нормализованном виде.
     *
     * @return void
     */
    public function testPolygonKeepsBezierHandles(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => [
                'type' => 'polygon',
                'points' => [[0, 0], [100, 0], [100, 100]],
                'handles' => [
                    ['in' => [0, 0], 'out' => [20, 0]],
                    ['in' => [-20, 0], 'out' => [0, 0]],
                    ['in' => [0, 0], 'out' => [0, 0]],
                ],
            ],
            'props' => ['kind' => 'water'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame('polygon', $element['shape']['type']);
        self::assertSame([20, 0], $element['shape']['handles'][0]['out']);
        self::assertSame([-20, 0], $element['shape']['handles'][1]['in']);
    }

    /**
     * Лента принимает ширины по вершинам.
     *
     * @return void
     */
    public function testPathKeepsWidths(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => [
                'type' => 'path',
                'points' => [[0, 400], [500, 400], [1000, 400]],
                'widths' => [40, 60, 40],
                'handles' => [
                    ['in' => [0, 0], 'out' => [100, 0]],
                    ['in' => [-100, 0], 'out' => [100, 0]],
                    ['in' => [-100, 0], 'out' => [0, 0]],
                ],
            ],
            'props' => ['kind' => 'water'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame('path', $element['shape']['type']);
        self::assertSame([40, 60, 40], $element['shape']['widths']);
    }

    /**
     * Ширины обязаны совпадать с числом вершин.
     *
     * @return void
     */
    public function testPathWidthsMustMatchPoints(): void
    {
        $this->expectException(SceneInvalidException::class);
        $this->expectExceptionMessage('widths is invalid');

        $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => [
                'type' => 'path',
                'points' => [[0, 400], [500, 400]],
                'widths' => [40],
            ],
            'props' => ['kind' => 'water'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
    }

    /**
     * Кромка террейна сохраняется вместе с шириной.
     *
     * @return void
     */
    public function testTerrainKeepsShore(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'water', 'shoreKind' => 'sand', 'shoreWidth' => 32],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame('sand', $element['props']['shoreKind']);
        self::assertSame(32, $element['props']['shoreWidth']);
    }

    /**
     * Остров сохраняет признак «поверх воды».
     *
     * @return void
     */
    public function testTerrainKeepsOverWater(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'polygon', 'points' => [[0, 0], [100, 0], [100, 100]]],
            'props' => ['kind' => 'grass', 'overWater' => true, 'shoreKind' => 'sand', 'shoreWidth' => 32],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertTrue($element['props']['overWater']);
        self::assertSame('sand', $element['props']['shoreKind']);
    }

    /**
     * Без кромки ширина обнуляется.
     *
     * @return void
     */
    public function testTerrainWithoutShoreHasZeroWidth(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'grass', 'shoreKind' => 'none', 'shoreWidth' => 32],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame('none', $element['props']['shoreKind']);
        self::assertSame(0, $element['props']['shoreWidth']);
    }

    /**
     * Глубина воды сохраняется, у остального террейна поле отбрасывается.
     *
     * @return void
     */
    public function testTerrainKeepsWaterDepthOnlyForWater(): void
    {
        $water = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'water', 'depth' => 32],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
        $grass = $this->validate([
            'id' => -2,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'grass', 'depth' => 32],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(32, $water['props']['depth']);
        self::assertArrayNotHasKey('depth', $grass['props']);
    }

    /**
     * Без глубины вода остаётся вровень с берегом.
     *
     * @return void
     */
    public function testWaterDepthDefaultsToZero(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'water'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(0, $element['props']['depth']);
    }

    /**
     * Отрицательная глубина отклоняется.
     *
     * @return void
     */
    public function testWaterDepthMustNotBeNegative(): void
    {
        $this->expectException(SceneInvalidException::class);

        $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'water', 'depth' => -1],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
    }

    /**
     * Неизвестный материал кромки отклоняется.
     *
     * @return void
     */
    public function testTerrainShoreKindMustBeKnown(): void
    {
        $this->expectException(SceneInvalidException::class);
        $this->expectExceptionMessage('shoreKind is unknown');

        $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'water', 'shoreKind' => 'lava', 'shoreWidth' => 32],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
    }

    /**
     * Скорость террейна по умолчанию зависит от типа.
     *
     * @return void
     */
    public function testTerrainSpeedDefaultsByKind(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'water'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(20, $element['props']['speed']);
    }

    /**
     * Явная скорость элемента сохраняется.
     *
     * @return void
     */
    public function testExplicitSpeedIsKept(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'grass', 'speed' => 35],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(35, $element['props']['speed']);
    }

    /**
     * Мост по умолчанию быстрее обычной опоры.
     *
     * @return void
     */
    public function testBridgeSpeedDefault(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'support',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'bridge'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(150, $element['props']['speed']);
    }

    /**
     * Съезд — допустимый подтип опоры со скоростью моста.
     *
     * @return void
     */
    public function testRampSpeedDefault(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'support',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'ramp'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame('ramp', $element['props']['kind']);
        self::assertSame(150, $element['props']['speed']);
    }

    /**
     * Лестница — допустимый подтип опоры с обычной скоростью.
     *
     * @return void
     */
    public function testStairsSpeedDefault(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'support',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'stairs'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame('stairs', $element['props']['kind']);
        self::assertSame(100, $element['props']['speed']);
    }

    /**
     * Поворот опоры сохраняется и ограничен 0–359.
     *
     * @return void
     */
    public function testSupportRotation(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'support',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'bridge', 'rotation' => 18],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(18, $element['props']['rotation']);
    }

    /**
     * Скорость вне диапазона отклоняется.
     *
     * @return void
     */
    public function testSpeedOutOfRangeIsRejected(): void
    {
        $this->expectException(SceneInvalidException::class);
        $this->expectExceptionMessage('speed');

        $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'terrain',
            'shape' => ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 100],
            'props' => ['kind' => 'grass', 'speed' => 10001],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
    }

    /**
     * Декор получает умолчания основания и силуэта.
     *
     * @return void
     */
    public function testDecorOcclusionDefaults(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'decor',
            'shape' => ['type' => 'point', 'x' => 500, 'y' => 400],
            'props' => ['scale' => 350, 'label' => 'Дерево'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(30, $element['props']['baseRadius']);
        self::assertSame(45, $element['props']['sightRadius']);
        self::assertSame(0, $element['props']['sightBottom']);
    }

    /**
     * Явные основание, силуэт и низ силуэта сохраняются.
     *
     * @return void
     */
    public function testDecorOcclusionIsKept(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'decor',
            'shape' => ['type' => 'point', 'x' => 500, 'y' => 400],
            'props' => ['scale' => 350, 'baseRadius' => 20, 'sightRadius' => 60, 'sightBottom' => 85],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(20, $element['props']['baseRadius']);
        self::assertSame(60, $element['props']['sightRadius']);
        self::assertSame(85, $element['props']['sightBottom']);
    }

    /**
     * Низ силуэта вне диапазона отклоняется.
     *
     * @return void
     */
    public function testDecorSightBottomOutOfRangeIsRejected(): void
    {
        $this->expectException(SceneInvalidException::class);
        $this->expectExceptionMessage('sightBottom');

        $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'decor',
            'shape' => ['type' => 'point', 'x' => 500, 'y' => 400],
            'props' => ['scale' => 350, 'sightBottom' => 401],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
    }

    /**
     * Основание и силуэт в единицах сцены сохраняются как есть.
     *
     * @return void
     */
    public function testDecorFootprintAndSilhouetteAreKept(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'decor',
            'shape' => ['type' => 'point', 'x' => 500, 'y' => 400],
            'props' => [
                'scale' => 350,
                'blocksVision' => false,
                'footprint' => ['shape' => 'circle', 'radius' => 42, 'offset' => [10, -5]],
                'silhouette' => [
                    'shape' => 'polygon',
                    'points' => [[-10, -20], [30, -20], [30, 20], [-10, 20]],
                    'handles' => [
                        ['in' => [0, 0], 'out' => [5, 0]],
                        ['in' => [-5, 0], 'out' => [0, 0]],
                        ['in' => [0, 0], 'out' => [0, 0]],
                        ['in' => [0, 0], 'out' => [0, 0]],
                    ],
                    'bottom' => 16,
                    'top' => 96,
                    'offset' => [-2, 3],
                ],
            ],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertFalse($element['props']['blocksVision']);
        self::assertSame(
            ['shape' => 'circle', 'radius' => 42, 'offset' => [10, -5]],
            $element['props']['footprint'],
        );
        self::assertSame(
            [
                'shape' => 'polygon',
                'points' => [[-10, -20], [30, -20], [30, 20], [-10, 20]],
                'handles' => [
                    ['in' => [0, 0], 'out' => [5, 0]],
                    ['in' => [-5, 0], 'out' => [0, 0]],
                    ['in' => [0, 0], 'out' => [0, 0]],
                    ['in' => [0, 0], 'out' => [0, 0]],
                ],
                'offset' => [-2, 3],
                'bottom' => 16,
                'top' => 96,
            ],
            $element['props']['silhouette'],
        );
    }

    /**
     * Локальная точка основания вне допуска отклоняется.
     *
     * @return void
     */
    public function testDecorFootprintPointOutOfRangeIsRejected(): void
    {
        $this->expectException(SceneInvalidException::class);
        $this->expectExceptionMessage('points[1][0]');

        $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'decor',
            'shape' => ['type' => 'point', 'x' => 500, 'y' => 400],
            'props' => [
                'footprint' => [
                    'shape' => 'polygon',
                    'points' => [[0, 0], [100001, 0], [0, 10]],
                ],
            ],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
    }

    /**
     * Полигон основания короче трёх точек отклоняется.
     *
     * @return void
     */
    public function testDecorFootprintWithTwoPointsIsRejected(): void
    {
        $this->expectException(SceneInvalidException::class);
        $this->expectExceptionMessage('footprint.points');

        $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'decor',
            'shape' => ['type' => 'point', 'x' => 500, 'y' => 400],
            'props' => ['footprint' => ['shape' => 'polygon', 'points' => [[0, 0], [10, 10]]]],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
    }

    /**
     * Окно сохраняет подоконник и перемычку.
     *
     * @return void
     */
    public function testOpeningKeepsSillAndLintel(): void
    {
        $element = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'opening',
            'shape' => ['type' => 'wall', 'points' => [[100, 100], [164, 100]], 'width' => 16],
            'props' => ['kind' => 'window', 'state' => 'closed', 'sill' => 32, 'lintel' => 96],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertSame(32, $element['props']['sill']);
        self::assertSame(96, $element['props']['lintel']);
    }

    /**
     * Закрытая дверь непрозрачна, окно — прозрачно по умолчанию.
     *
     * @return void
     */
    public function testOpeningBlocksVisionByKind(): void
    {
        $door = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'opening',
            'shape' => ['type' => 'wall', 'points' => [[100, 100], [164, 100]], 'width' => 16],
            'props' => ['kind' => 'door', 'state' => 'closed'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);
        $window = $this->validate([
            'id' => -1,
            'boardId' => 10,
            'kind' => 'opening',
            'shape' => ['type' => 'wall', 'points' => [[100, 100], [164, 100]], 'width' => 16],
            'props' => ['kind' => 'window', 'state' => 'closed'],
            'sortOrder' => 0,
            'targetBoardId' => null,
        ]);

        self::assertTrue($door['props']['blocksVision']);
        self::assertFalse($window['props']['blocksVision']);
    }

    /**
     * Проверяет один элемент на поле 1000×800.
     *
     * @param array<string|int, mixed> $row Элемент.
     *
     * @return array<string, mixed> Нормализованный элемент.
     */
    private function validate(array $row): array
    {
        $validator = new SceneElementValidator(
            new SceneSaveFields(),
            new SceneSaveFileResolver($this->files()),
            new SceneBoardBounds(),
        );

        $elements = $validator->validate([$row], [
            ['id' => 10, 'boundWidth' => 1000, 'boundHeight' => 800],
        ]);

        return $elements[0];
    }

    /**
     * Порт файлов, который не должен вызываться без ассета.
     *
     * @return IFiles Порт.
     */
    private function files(): IFiles
    {
        return new class () implements IFiles {
            public function requireReadable(string $publicId): never
            {
                throw new \RuntimeException('unexpected file read');
            }

            public function requireOwned(string $publicId): never
            {
                throw new \RuntimeException('unexpected file read');
            }

            public function archive(string $publicId): never
            {
                throw new \RuntimeException('unexpected file archive');
            }

            public function copyOwnedTo(string $publicId, string $targetPath): never
            {
                throw new \RuntimeException('unexpected file copy');
            }

            public function storeDerived(string $sourcePublicId, string $sourcePath, string $originalName): never
            {
                throw new \RuntimeException('unexpected file store');
            }

            /**
             * @param array<int, int> $fileIds
             *
             * @return array<int, string>
             */
            public function activePublicIds(array $fileIds): array
            {
                return [];
            }

            /**
             * @param array<int, int> $fileIds
             *
             * @return array<int, \Mifrial\Core\File\Dto\FileRecord>
             */
            public function activeFiles(array $fileIds): array
            {
                return [];
            }
        };
    }
}
