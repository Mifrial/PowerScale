<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\AbilitySpecs;
use Mifrial\Roleplay\Rule\Spec\ProcessSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Парсер процесса v2: счётчики, пакет удара, пороги и закрытие.
 */
final class ProcessSpecV2Test extends TestCase
{
    /**
     * Старая ревизия: нет counters и target_mode — пустые умолчания.
     *
     * @return void
     */
    public function testLegacyDefaults(): void
    {
        $spec = $this->read([
            'steps' => [['code' => 'one', 'name' => 'One']],
        ]);

        self::assertSame([], $spec->getCounters());
        self::assertSame(ProcessSpec::TARGET_ANY, $spec->getTargetMode());
        self::assertFalse($spec->isSingleTarget());
        self::assertNull($spec->getSteps()[0]->getStrike());
        self::assertSame([], $spec->getSteps()[0]->getRequiresCounters());
    }

    /**
     * Счётчики: старт по умолчанию 0, явный initial читается.
     *
     * @return void
     */
    public function testCounters(): void
    {
        $spec = $this->read([
            'counters' => [
                ['counter_code' => 'combo'],
                ['counter_code' => 'marks', 'initial' => 2],
            ],
            'steps' => [['code' => 'one', 'name' => 'One']],
            'target_mode' => 'single',
        ]);

        self::assertCount(2, $spec->getCounters());
        self::assertSame('combo', $spec->getCounters()[0]->getCode());
        self::assertSame(0, $spec->getCounters()[0]->getInitial());
        self::assertSame(2, $spec->getCounters()[1]->getInitial());
        self::assertTrue($spec->isSingleTarget());
    }

    /**
     * Дубль кода счётчика — отказ.
     *
     * @return void
     */
    public function testDuplicateCounterIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->read([
            'counters' => [
                ['counter_code' => 'combo'],
                ['counter_code' => 'combo'],
            ],
            'steps' => [['code' => 'one', 'name' => 'One']],
        ]);
    }

    /**
     * Неизвестный target_mode — отказ.
     *
     * @return void
     */
    public function testUnknownTargetModeIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->read([
            'target_mode' => 'last',
            'steps' => [['code' => 'one', 'name' => 'One']],
        ]);
    }

    /**
     * Пакет удара читается вместе с формулами и порогами.
     *
     * @return void
     */
    public function testStrikePackageAndThresholds(): void
    {
        $spec = $this->read([
            'counters' => [['counter_code' => 'combo']],
            'steps' => [[
                'code' => 'finish',
                'name' => 'Finish',
                'execution' => ['type' => 'strike'],
                'requires_counters' => [['counter_code' => 'combo', 'min' => 2]],
                'strike' => [
                    'advantage' => ['type' => 'counter', 'counter_code' => 'combo', 'max_value' => 3],
                    'extra_successes' => [
                        'type' => 'counter',
                        'counter_code' => 'combo',
                        'divisor' => 2,
                        'max_value' => 3,
                    ],
                ],
                'counter_on_success' => [['counter_code' => 'combo', 'set' => 0]],
            ]],
        ]);

        $step = $spec->getSteps()[0];
        self::assertFalse($step->getRequiresCounters()[0]->isMet(['combo' => 1]));
        self::assertTrue($step->getRequiresCounters()[0]->isMet(['combo' => 2]));

        $strike = $step->getStrike();
        self::assertNotNull($strike);
        self::assertSame(3, $strike->advantageOf(['combo' => 5]));
        self::assertSame(1, $strike->extraSuccessesOf(['combo' => 3]));

        $success = $step->getCounterOnSuccess()[0];
        self::assertSame('combo', $success->getCounterCode());
        self::assertSame(0, $success->apply(4));
    }

    /**
     * fixed-пакет и delta-операция.
     *
     * @return void
     */
    public function testFixedStrikeAndDeltaCounter(): void
    {
        $spec = $this->read([
            'counters' => [['counter_code' => 'combo']],
            'steps' => [[
                'code' => 'start',
                'name' => 'Start',
                'strike' => ['advantage' => ['type' => 'fixed', 'value' => -1]],
                'counter_on_success' => [['counter_code' => 'combo', 'delta' => 1]],
            ]],
        ]);

        self::assertSame(-1, $spec->getSteps()[0]->getStrike()->advantageOf([]));
        self::assertSame(1, $spec->getSteps()[0]->getCounterOnSuccess()[0]->apply(0));
    }

    /**
     * strike у неатакующего шага — отказ.
     *
     * @return void
     */
    public function testStrikeOnActionStepIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->read([
            'steps' => [[
                'code' => 'act',
                'name' => 'Act',
                'execution' => ['type' => 'action', 'action_code' => 'swing'],
                'strike' => ['advantage' => ['type' => 'fixed', 'value' => 1]],
            ]],
        ]);
    }

    /**
     * Ссылка на необъявленный счётчик — отказ.
     *
     * @return void
     */
    public function testUndeclaredCounterIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->read([
            'steps' => [[
                'code' => 'start',
                'name' => 'Start',
                'requires_counters' => [['counter_code' => 'combo', 'min' => 1]],
            ]],
        ]);
    }

    /**
     * delta и set одновременно — отказ.
     *
     * @return void
     */
    public function testDeltaAndSetTogetherIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->read([
            'counters' => [['counter_code' => 'combo']],
            'steps' => [[
                'code' => 'start',
                'name' => 'Start',
                'counter_on_success' => [['counter_code' => 'combo', 'delta' => 1, 'set' => 0]],
            ]],
        ]);
    }

    /**
     * Ни delta, ни set — отказ.
     *
     * @return void
     */
    public function testEmptyCounterOpIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->read([
            'counters' => [['counter_code' => 'combo']],
            'steps' => [[
                'code' => 'start',
                'name' => 'Start',
                'counter_on_success' => [['counter_code' => 'combo']],
            ]],
        ]);
    }

    /**
     * divisor не больше нуля — отказ.
     *
     * @return void
     */
    public function testNonPositiveDivisorIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        $this->read([
            'counters' => [['counter_code' => 'combo']],
            'steps' => [[
                'code' => 'start',
                'name' => 'Start',
                'strike' => [
                    'extra_successes' => [
                        'type' => 'counter',
                        'counter_code' => 'combo',
                        'divisor' => 0,
                    ],
                ],
            ]],
        ]);
    }

    /**
     * Закрытие читается у type: skill.
     *
     * @return void
     */
    public function testClosesParentProcessOnSkill(): void
    {
        $spec = AbilitySpecs::read([
            'type' => 'skill',
            'parent_ability_code' => 'kombinatsiya-udarov',
            'closes_parent_process' => [
                'requires_counters' => [['counter_code' => 'combo', 'min' => 2]],
                'strike' => [
                    'advantage' => ['type' => 'counter', 'counter_code' => 'combo', 'max_value' => 3],
                    'extra_successes' => ['type' => 'counter', 'counter_code' => 'combo', 'divisor' => 2],
                ],
            ],
        ]);

        $close = $spec->getBase()->getClosesParentProcess();
        self::assertNotNull($close);
        self::assertSame('kombinatsiya-udarov', $spec->getBase()->getParentAbilityCode());
        self::assertFalse($close->meets(['combo' => 1]));
        self::assertTrue($close->meets(['combo' => 2]));
        self::assertSame(3, $close->getStrike()->advantageOf(['combo' => 4]));
        self::assertSame(2, $close->getStrike()->extraSuccessesOf(['combo' => 4]));
    }

    /**
     * Закрытие без parent_ability_code — отказ формы.
     *
     * @return void
     */
    public function testClosesParentProcessWithoutParentIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        AbilitySpecs::read([
            'type' => 'skill',
            'closes_parent_process' => [
                'strike' => ['advantage' => ['type' => 'fixed', 'value' => 1]],
            ],
        ]);
    }

    /**
     * Читает процесс без обвязки карточки.
     *
     * @param array<string, mixed> $process Тело process.
     *
     * @return ProcessSpec Тело.
     */
    private function read(array $process): ProcessSpec
    {
        return ProcessSpecs::read(['type' => 'process', 'process' => $process]);
    }

    /**
     * Убеждается, что шаг собран с нужным кодом.
     *
     * @return void
     */
    public function testStepCodeIsKept(): void
    {
        $spec = $this->read([
            'steps' => [['code' => 'combo-start', 'name' => 'Start']],
        ]);

        self::assertInstanceOf(ProcessStep::class, $spec->getSteps()[0]);
        self::assertSame('combo-start', $spec->getSteps()[0]->getCode());
    }
}
