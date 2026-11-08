<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Tests;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Service\MapSaveRows;
use PHPUnit\Framework\TestCase;

/**
 * Общие операции над строками снимка: деление, id и удаление пропавших.
 */
final class MapSaveRowsTest extends TestCase
{
    private MapSaveRows $rows;

    /**
     * Свежий помощник.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->rows = new MapSaveRows();
    }

    /**
     * Отрицательный id — вставка, известный — обновление, порядок сохранён.
     *
     * @return void
     */
    public function testSplitSeparatesInsertsAndUpdates(): void
    {
        $first = ['id' => -1];
        $second = ['id' => 5];
        $third = ['id' => -2];
        [$updates, $inserts] = $this->rows->split([$first, $second, $third], [5 => true], 'Map row is unknown');

        self::assertSame([5 => $second], $updates);
        self::assertSame([$first, $third], $inserts);
    }

    /**
     * Положительный id неизвестной строки — ошибка с переданным текстом.
     *
     * @return void
     */
    public function testSplitRejectsUnknownRow(): void
    {
        $this->expectException(MapInvalidException::class);
        $this->expectExceptionMessage('Map node is unknown');

        $this->rows->split([['id' => 7]], [5 => true], 'Map node is unknown');
    }

    /**
     * Обновления получают карту id на самих себя, вставки в неё не попадают.
     *
     * @return void
     */
    public function testIdsMapsSnapshotIdToItself(): void
    {
        self::assertSame([3 => 3, 8 => 8], $this->rows->ids([3 => ['id' => 3], 8 => ['id' => 8]]));
        self::assertSame([], $this->rows->ids([]));
    }

    /**
     * Пропавшие строки перечисляются по id, порядок существующих сохранён.
     *
     * @return void
     */
    public function testStaleListsMissingIds(): void
    {
        $existing = [1 => ['id' => 1], 2 => ['id' => 2], 3 => ['id' => 3]];
        $updates = [1 => ['id' => 1], 3 => ['id' => 3]];

        self::assertSame([2], $this->rows->stale($existing, $updates));
        self::assertSame([], $this->rows->stale($existing, $existing));
    }
}
