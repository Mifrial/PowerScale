<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Tests;

use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Service\SceneSaveRows;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет реконсиляцию строк снимка локации.
 */
final class SceneSaveRowsTest extends TestCase
{
    /**
     * Отрицательные id — вставка, положительные — обновление, пропавшие — удаление.
     *
     * @return void
     */
    public function testSplitIdsAndStale(): void
    {
        $rows = new SceneSaveRows();
        $snapshot = [
            ['id' => -1, 'name' => 'new'],
            ['id' => 5, 'name' => 'keep'],
            ['id' => 7, 'name' => 'keep'],
        ];
        $existing = [5 => true, 6 => true, 7 => true];

        [$updates, $inserts] = $rows->split($snapshot, $existing, 'unknown');

        self::assertSame([5, 7], array_keys($updates));
        self::assertCount(1, $inserts);
        self::assertSame(-1, $inserts[0]['id']);
        self::assertSame([5 => 5, 7 => 7], $rows->ids($updates));
        self::assertSame([6], $rows->stale($existing, $updates));
    }

    /**
     * Положительный id, которого нет в строке, — ошибка.
     *
     * @return void
     */
    public function testUnknownPositiveIdIsInvalid(): void
    {
        $rows = new SceneSaveRows();

        $this->expectException(SceneInvalidException::class);
        $this->expectExceptionMessage('unknown');
        $rows->split([['id' => 9]], [5 => true], 'unknown');
    }
}
