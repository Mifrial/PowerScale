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
        };
    }
}
