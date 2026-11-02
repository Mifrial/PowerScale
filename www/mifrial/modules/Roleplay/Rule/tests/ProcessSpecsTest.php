<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessExecution;
use Mifrial\Roleplay\Rule\Spec\ProcessSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Умолчания ProcessSpec для старых ревизий.
 */
final class ProcessSpecsTest extends TestCase
{
    /**
     * Пустой старт — первый шаг, operations различается по ключу.
     *
     * @return void
     */
    public function testLegacyStepDefaults(): void
    {
        $spec = ProcessSpecs::read([
            'type' => 'process',
            'process' => [
                'steps' => [
                    ['code' => 'first', 'name' => 'First', 'operations' => []],
                    ['code' => 'second', 'name' => 'Second'],
                ],
            ],
        ]);

        self::assertSame('first', $spec->getStartStepCode());
        self::assertSame(ProcessExecution::TYPE_NONE, $spec->getSteps()[0]->getExecution()->getType());
        self::assertSame(ProcessExecution::TYPE_STRIKE, $spec->getSteps()[1]->getExecution()->getType());
    }

    /**
     * Явный execution читается с action_code.
     *
     * @return void
     */
    public function testExplicitExecutionIsParsed(): void
    {
        $spec = ProcessSpecs::read([
            'process' => [
                'steps' => [[
                    'code' => 'act',
                    'name' => 'Act',
                    'execution' => ['type' => 'action', 'action_code' => 'swing'],
                ]],
            ],
        ]);

        $execution = $spec->getSteps()[0]->getExecution();
        self::assertSame(ProcessExecution::TYPE_ACTION, $execution->getType());
        self::assertSame('swing', $execution->getActionCode());
        self::assertSame('act', $spec->getStartStepCode());
    }
}
