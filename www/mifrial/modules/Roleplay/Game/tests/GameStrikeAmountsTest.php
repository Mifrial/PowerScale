<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameStrikeAmounts;
use PHPUnit\Framework\TestCase;

/**
 * Потолок типа урона режет только множитель повреждения.
 */
final class GameStrikeAmountsTest extends TestCase
{
    /**
     * Без потолка остаётся полный рейтинг.
     *
     * @return void
     */
    public function testAppliedSrKeepsFullRatingWithoutCap(): void
    {
        self::assertSame(5, (new GameStrikeAmounts())->appliedSr(5, null));
    }

    /**
     * Потолок ниже рейтинга заменяет его.
     *
     * @return void
     */
    public function testAppliedSrStopsAtCap(): void
    {
        self::assertSame(3, (new GameStrikeAmounts())->appliedSr(5, 3));
        self::assertSame(2, (new GameStrikeAmounts())->appliedSr(2, 3));
    }

    /**
     * Нулевой потолок обнуляет множитель, отрицательный — не даёт отрицательного.
     *
     * @return void
     */
    public function testAppliedSrFloorsAtZero(): void
    {
        self::assertSame(0, (new GameStrikeAmounts())->appliedSr(4, 0));
        self::assertSame(0, (new GameStrikeAmounts())->appliedSr(4, -2));
    }
}
