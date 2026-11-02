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

    /**
     * Стартовые счётчики берутся из спеки.
     *
     * @return void
     */
    public function testInitialCounters(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'counters' => [
                    ['counter_code' => 'combo', 'initial' => 1],
                    ['counter_code' => 'marks'],
                ],
                'steps' => [['code' => 'a', 'name' => 'A']],
            ],
        ]);

        self::assertSame(['combo' => 1, 'marks' => 0], (new GameProcessRules())->initialCounters($spec));
    }

    /**
     * Порог скрывает шаг, а pending не фильтруется.
     *
     * @return void
     */
    public function testCounterThresholdFiltersSteps(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'counters' => [['counter_code' => 'combo']],
                'steps' => [
                    ['code' => 'build', 'name' => 'Build'],
                    [
                        'code' => 'finish',
                        'name' => 'Finish',
                        'requires_counters' => [['counter_code' => 'combo', 'min' => 2]],
                    ],
                ],
                'transition' => [
                    'mode' => 'custom',
                    'edges' => [
                        ['from' => 'build', 'to' => 'build'],
                        ['from' => 'build', 'to' => 'finish'],
                    ],
                ],
            ],
        ]);
        $rules = new GameProcessRules();

        self::assertSame(['build'], $rules->availableSteps($spec, 'build', 'completed', ['combo' => 1]));
        self::assertSame(['build', 'finish'], $rules->availableSteps($spec, 'build', 'completed', ['combo' => 2]));
        self::assertSame(['finish'], $rules->availableSteps($spec, 'finish', 'pending', []));
    }

    /**
     * Порог стартового шага проверяется по стартовым счётчикам.
     *
     * @return void
     */
    public function testStartThresholdUsesInitialCounters(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'counters' => [['counter_code' => 'combo']],
                'steps' => [[
                    'code' => 'start',
                    'name' => 'Start',
                    'requires_counters' => [['counter_code' => 'combo', 'min' => 1]],
                ]],
            ],
        ]);

        self::assertSame([], (new GameProcessRules())->availableSteps($spec, null, null, ['combo' => 0]));
        self::assertSame(['start'], (new GameProcessRules())->availableSteps($spec, null, null, ['combo' => 1]));
    }

    /**
     * Успех применяет counter_on_success по порядку списка.
     *
     * @return void
     */
    public function testCountersAfterSuccessKeepOrder(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'counters' => [['counter_code' => 'combo', 'initial' => 4]],
                'steps' => [[
                    'code' => 'a',
                    'name' => 'A',
                    'counter_on_success' => [
                        ['counter_code' => 'combo', 'set' => 2],
                        ['counter_code' => 'combo', 'delta' => 1],
                    ],
                ]],
            ],
        ]);
        $rules = new GameProcessRules();
        $step = $rules->step($spec, 'a');

        self::assertSame(['combo' => 3], $rules->countersAfterStep($step, ['combo' => 4], true, 'end_action'));
    }

    /**
     * counter_on_failure работает только при restart_from_first.
     *
     * @return void
     */
    public function testCounterOnFailureOnlyRestarts(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'counters' => [['counter_code' => 'combo', 'initial' => 3]],
                'failure' => 'end_action',
                'steps' => [[
                    'code' => 'a',
                    'name' => 'A',
                    'counter_on_success' => [['counter_code' => 'combo', 'set' => 0]],
                    'counter_on_failure' => [['counter_code' => 'combo', 'delta' => -1]],
                ]],
            ],
        ]);
        $rules = new GameProcessRules();
        $step = $rules->step($spec, 'a');

        self::assertSame(['combo' => 3], $rules->countersAfterStep($step, ['combo' => 3], false, 'end_action'));
        self::assertSame(['combo' => 2], $rules->countersAfterStep($step, ['combo' => 3], false, 'restart_from_first'));
        self::assertSame(['combo' => 0], $rules->countersAfterStep($step, ['combo' => 3], true, 'restart_from_first'));
    }
}
