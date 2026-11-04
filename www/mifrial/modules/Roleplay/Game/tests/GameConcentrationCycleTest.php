<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameConcentrationCycle;
use PHPUnit\Framework\TestCase;

final class GameConcentrationCycleTest extends TestCase
{
    /**
     * Трата, конец хода и начало следующего.
     *
     * @return void
     */
    public function testCycle(): void
    {
        $cycle = new GameConcentrationCycle();
        $key = 'character:1';
        self::assertTrue($cycle->canSpend([], $key));

        $used = $cycle->markUsed([], $key);
        self::assertFalse($cycle->endTurn($used, $key)['refill']);
        $afterSpend = $cycle->endTurn($used, $key)['cycle'];
        self::assertTrue($cycle->canSpend($afterSpend, $key));

        $fresh = [];
        $ended = $cycle->endTurn($fresh, $key);
        self::assertTrue($ended['refill']);
        self::assertFalse($cycle->canSpend($ended['cycle'], $key));
        $begun = $cycle->beginTurn($ended['cycle'], $key);
        self::assertTrue($cycle->canSpend($begun, $key));
    }
}
