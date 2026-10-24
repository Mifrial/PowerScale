<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GamePreparedDefense;
use PHPUnit\Framework\TestCase;

/**
 * Подготовка против защиты: замена и преимущество по реакции.
 */
final class GamePreparedDefenseTest extends TestCase
{
    /**
     * Та же реакция даёт +1, другая −1, чужая цель — null.
     *
     * @return void
     */
    public function testModifierUsesTargetAndReaction(): void
    {
        $prepared = new GamePreparedDefense();
        $pending = $prepared->replace([], 'character', 1, 'character:2', 'dodge', 'counter');

        self::assertSame(1, $prepared->modifier($pending, 'character', 1, 'character:2', 'dodge')?->getDelta());
        self::assertSame(-1, $prepared->modifier($pending, 'character', 1, 'character:2', 'block')?->getDelta());
        self::assertNull($prepared->modifier($pending, 'character', 1, 'character:3', 'dodge'));
        self::assertNull($prepared->modifier($pending, 'character', 2, 'character:2', 'dodge'));
    }

    /**
     * Повторная запись заменяет прошлую подготовку.
     *
     * @return void
     */
    public function testReplaceKeepsSinglePreparedCounter(): void
    {
        $prepared = new GamePreparedDefense();
        $pending = $prepared->replace([], 'character', 1, 'character:2', 'dodge', 'counter');
        $pending = $prepared->replace($pending, 'character', 1, 'npc:5', 'ignore', 'counter');

        $entries = $pending['character:1'] ?? [];
        self::assertCount(1, $entries);
        self::assertSame('npc:5', $entries[0]['effect']['targetKey'] ?? null);
        self::assertSame('ignore', $entries[0]['effect']['reaction'] ?? null);
        self::assertNull($prepared->modifier($pending, 'character', 1, 'character:2', 'dodge'));
        self::assertSame(1, $prepared->modifier($pending, 'character', 1, 'npc:5', 'ignore')?->getDelta());
    }
}
