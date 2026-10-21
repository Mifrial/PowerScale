<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Service\GameStrikeConsequences;
use Mifrial\Roleplay\Mechanic\Service\Handler\BluntUnconsciousHandler;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Канон blunt_unconscious: порог РУ и урон не ниже стойкости — оба условия.
 */
final class GameStrikeConsequencesBluntTest extends TestCase
{
    /**
     * Одно условие без другого не сбивает цель с ног.
     *
     * @return void
     */
    public function testBluntKnockoutRequiresRatingAndInjury(): void
    {
        $subject = (new ReflectionClass(GameStrikeConsequences::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GameStrikeConsequences::class, 'blunt');
        $method->setAccessible(true);
        $slice = new CharacterRuleSlice(1, 1, 'test', [], []);
        $hooks = [[
            'handler' => new BluntUnconsciousHandler(),
            'payload' => ['successAtLeast' => 6],
        ]];
        $endurance = new DimensionalNumber(4, 0);

        self::assertTrue($method->invoke($subject, $hooks, 6, new DimensionalNumber(8, 0), $slice, [], $endurance));
        self::assertFalse($method->invoke($subject, $hooks, 5, new DimensionalNumber(8, 0), $slice, [], $endurance));
        self::assertFalse($method->invoke($subject, $hooks, 6, new DimensionalNumber(3, 0), $slice, [], $endurance));
    }
}
