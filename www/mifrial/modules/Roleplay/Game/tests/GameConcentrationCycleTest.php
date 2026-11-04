<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameConcentrationCycle;
use PHPUnit\Framework\TestCase;

final class GameConcentrationCycleTest extends TestCase
{
    /**
     * Трата в цикле запрещает refill, чистый цикл восстанавливает жетоны.
     *
     * @return void
     */
    public function testCycle(): void
    {
        $cycle = new GameConcentrationCycle();
        $key = 'character:1';

        $used = $cycle->markUsed([], $key);
        self::assertFalse($cycle->endTurn($used, $key)['refill']);

        $ended = $cycle->endTurn([], $key);
        self::assertTrue($ended['refill']);
        self::assertFalse($ended['cycle'][$key]['used']);
    }
}
