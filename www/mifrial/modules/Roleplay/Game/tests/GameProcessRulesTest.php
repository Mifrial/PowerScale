<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameProcessRules;
use Mifrial\Roleplay\Rule\Spec\ProcessSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Переходы и цена шага процесса.
 */
final class GameProcessRulesTest extends TestCase
{
    /**
     * Custom-рёбра и exit-набор.
     *
     * @return void
     */
    public function testCustomStepsAndExit(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'steps' => [
                    ['code' => 'a', 'name' => 'A'],
                    ['code' => 'b', 'name' => 'B'],
                    ['code' => 'c', 'name' => 'C'],
                ],
                'start_step_code' => 'a',
                'exit_step_codes' => ['c'],
                'transition' => [
                    'mode' => 'custom',
                    'edges' => [
                        ['from' => 'a', 'to' => 'b'],
                        ['from' => 'b', 'to' => 'c'],
                    ],
                ],
            ],
        ]);
        $rules = new GameProcessRules();

        self::assertSame(['b'], $rules->availableSteps($spec, 'a', 'completed'));
        self::assertSame(['c'], $rules->availableSteps($spec, 'b', 'completed'));
        self::assertSame([], $rules->availableSteps($spec, 'c', 'completed'));
        self::assertSame(['b'], $rules->availableSteps($spec, 'b', 'pending'));
    }

    /**
     * Chain идёт вперёд на max_shift.
     *
     * @return void
     */
    public function testChainDirection(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'steps' => [
                    ['code' => 'a', 'name' => 'A'],
                    ['code' => 'b', 'name' => 'B'],
                    ['code' => 'c', 'name' => 'C'],
                ],
                'transition' => ['mode' => 'chain', 'direction' => 'forward', 'max_shift' => 2],
            ],
        ]);

        self::assertSame(['b', 'c'], (new GameProcessRules())->availableSteps($spec, 'a', 'completed'));
    }
}
