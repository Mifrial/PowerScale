<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CounterOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CounterThreshold;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessFormula;
use PHPUnit\Framework\TestCase;

/**
 * Формула процесса и операции со счётчиками.
 */
final class ProcessFormulaTest extends TestCase
{
    /**
     * fixed не зависит от счётчиков.
     *
     * @return void
     */
    public function testFixedIgnoresCounters(): void
    {
        self::assertSame(-1, ProcessFormula::fixed(-1)->evaluate(['combo' => 5]));
        self::assertSame(2, ProcessFormula::fixed(2)->evaluate([]));
    }

    /**
     * counter: floor(счётчик × factor / divisor).
     *
     * @return void
     */
    public function testCounterFactorAndDivisor(): void
    {
        self::assertSame(3, ProcessFormula::counter('combo', 2, 2)->evaluate(['combo' => 3]));
        self::assertSame(1, ProcessFormula::counter('combo', 1, 2)->evaluate(['combo' => 3]));
        self::assertSame(3, ProcessFormula::counter('combo')->evaluate(['combo' => 3]));
    }

    /**
     * Отрицательный factor округляется вниз, а не к нулю.
     *
     * @return void
     */
    public function testNegativeFactorFloors(): void
    {
        self::assertSame(-2, ProcessFormula::counter('combo', -1, 2)->evaluate(['combo' => 3]));
        self::assertSame(-1, ProcessFormula::counter('combo', -1, 2)->evaluate(['combo' => 1]));
        self::assertSame(0, ProcessFormula::counter('combo', -1, 2)->evaluate(['combo' => 0]));
    }

    /**
     * Сначала min_value, затем max_value.
     *
     * @return void
     */
    public function testBoundsOrder(): void
    {
        $capped = ProcessFormula::counter('combo', 1, 1, null, 3);
        self::assertSame(3, $capped->evaluate(['combo' => 9]));

        $raised = ProcessFormula::counter('combo', 1, 1, 2, null);
        self::assertSame(2, $raised->evaluate(['combo' => 0]));

        $both = ProcessFormula::counter('combo', 1, 1, 4, 2);
        self::assertSame(2, $both->evaluate(['combo' => 9]));
    }

    /**
     * Неизвестный счётчик — ноль.
     *
     * @return void
     */
    public function testUnknownCounterIsZero(): void
    {
        self::assertSame(0, ProcessFormula::counter('combo')->evaluate([]));
        self::assertSame(0, ProcessFormula::counter('combo')->evaluate(['combo' => '3']));
    }

    /**
     * delta и set меняют значение по-разному.
     *
     * @return void
     */
    public function testCounterOps(): void
    {
        self::assertSame(3, CounterOp::delta('combo', 1)->apply(2));
        self::assertSame(1, CounterOp::delta('combo', -1)->apply(2));
        self::assertSame(0, CounterOp::set('combo', 0)->apply(5));
        self::assertTrue(CounterOp::set('combo', 0)->isSet());
        self::assertFalse(CounterOp::delta('combo', 1)->isSet());
        self::assertSame('combo', CounterOp::delta('combo', 1)->getCounterCode());
    }

    /**
     * Пороги складываются через AND, min включительный.
     *
     * @return void
     */
    public function testThresholds(): void
    {
        $thresholds = [new CounterThreshold('combo', 2), new CounterThreshold('other', 1)];

        self::assertTrue(CounterThreshold::areMet([], []));
        self::assertTrue(CounterThreshold::areMet($thresholds, ['combo' => 2, 'other' => 1]));
        self::assertFalse(CounterThreshold::areMet($thresholds, ['combo' => 1, 'other' => 1]));
        self::assertFalse(CounterThreshold::areMet($thresholds, ['combo' => 2]));
    }
}
