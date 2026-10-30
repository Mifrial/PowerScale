<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceSpecRejectedException;
use Mifrial\Roleplay\RuleSpace\Service\RuleRevisionSpecGuard;
use PHPUnit\Framework\TestCase;

/**
 * Отказ публикации при битой spec put.
 */
final class RuleRevisionSpecGuardTest extends TestCase
{
    /**
     * Scalar-параметр парой не публикуется.
     *
     * @return void
     */
    public function testBrokenPutSpecRejects(): void
    {
        $guard = new RuleRevisionSpecGuard($this->createMock(IRules::class));
        try {
            $guard->assertEntries(1, [
                RuleCommitEntry::put('innate-strength', new RuleVersionBody(
                    'ability',
                    'Врождённая Сила',
                    '',
                    [
                        'type' => 'trait',
                        'parameters' => [[
                            'code' => 'x',
                            'label' => 'X',
                            'kind' => 'scalar',
                            'resolution' => 'purchase',
                            'default' => ['base' => 0, 'size' => 0],
                        ]],
                    ],
                    [],
                    [],
                    'needs_work',
                )),
            ], null);
            self::fail('broken spec must reject');
        } catch (RuleSpaceSpecRejectedException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
            self::assertSame('innate-strength', $exception->getErrorDetails()['problems'][0]['ruleCode']);
            self::assertSame(['spec.default'], $exception->getErrorDetails()['problems'][0]['messages']);
        }
    }

    /**
     * Ссылка прироста на отсутствующую проверку не публикуется.
     *
     * @return void
     */
    public function testMissingIncreaseCheckRejects(): void
    {
        $guard = new RuleRevisionSpecGuard($this->createMock(IRules::class));
        try {
            $guard->assertEntries(1, [
                RuleCommitEntry::put('unstable', new RuleVersionBody('state', 'Unstable', '', [
                    'value_type' => 'number',
                    'aggregation' => 'sum',
                    'increase_check_code' => 'check-unstable',
                ], [], [], 'needs_work')),
            ], null);
            self::fail('missing increase check must reject');
        } catch (RuleSpaceSpecRejectedException $exception) {
            self::assertSame('unstable', $exception->getErrorDetails()['problems'][0]['ruleCode']);
            self::assertSame(
                ['state.increase_check_code: проверка «check-unstable» не найдена'],
                $exception->getErrorDetails()['problems'][0]['messages'],
            );
        }
    }

    /**
     * Проверка прироста обязана брать сложность из этого состояния.
     *
     * @return void
     */
    public function testIncreaseCheckDifficultyRejects(): void
    {
        $guard = new RuleRevisionSpecGuard($this->createMock(IRules::class));
        try {
            $guard->assertEntries(1, [
                RuleCommitEntry::put('unstable', new RuleVersionBody('state', 'Unstable', '', [
                    'value_type' => 'number',
                    'aggregation' => 'sum',
                    'increase_check_code' => 'check-unstable',
                ], [], [], 'needs_work')),
                RuleCommitEntry::put('check-unstable', new RuleVersionBody('check', 'Check', '', [
                    'difficulty_input' => ['kind' => 'ask'],
                ], [], [], 'needs_work')),
            ], null);
            self::fail('foreign increase check difficulty must reject');
        } catch (RuleSpaceSpecRejectedException $exception) {
            self::assertSame('unstable', $exception->getErrorDetails()['problems'][0]['ruleCode']);
            self::assertContains(
                'state.increase_check_code: проверка «check-unstable» должна брать сложность из состояния «unstable»',
                $exception->getErrorDetails()['problems'][0]['messages'],
            );
        }
    }

    /**
     * Исход проверки на отсутствующее состояние не публикуется.
     *
     * @return void
     */
    public function testMissingOutcomeStateRejects(): void
    {
        $guard = new RuleRevisionSpecGuard($this->createMock(IRules::class));
        try {
            $guard->assertEntries(1, [
                RuleCommitEntry::put('check-unstable', new RuleVersionBody('check', 'Check', '', [
                    'difficulty_input' => ['kind' => 'ask'],
                    'outcomes' => [['max_rating' => 0, 'state_rule_code' => 'missing']],
                ], [], [], 'needs_work')),
            ], null);
            self::fail('missing outcome state must reject');
        } catch (RuleSpaceSpecRejectedException $exception) {
            self::assertSame('check-unstable', $exception->getErrorDetails()['problems'][0]['ruleCode']);
            self::assertSame(
                ['check.outcomes: состояние «missing» не найдено'],
                $exception->getErrorDetails()['problems'][0]['messages'],
            );
        }
    }

    /**
     * Связка прироста с живыми ссылками публикуется.
     *
     * @return void
     */
    public function testIncreaseCheckWithLiveReferencesPasses(): void
    {
        $guard = new RuleRevisionSpecGuard($this->createMock(IRules::class));
        $guard->assertEntries(1, [
            RuleCommitEntry::put('unstable', new RuleVersionBody('state', 'Unstable', '', [
                'value_type' => 'number',
                'aggregation' => 'sum',
                'increase_check_code' => 'check-unstable',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('lying', new RuleVersionBody('state', 'Lying', '', [
                'value_type' => 'flag',
                'aggregation' => 'max',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('check-unstable', new RuleVersionBody('check', 'Check', '', [
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'unstable'],
                'outcomes' => [['max_rating' => 0, 'state_rule_code' => 'lying']],
            ], [], [], 'needs_work')),
        ], null);

        self::assertTrue(true);
    }

    /**
     * Броня со скалярным resistance_slots не публикуется.
     *
     * @return void
     */
    public function testBrokenItemArmorSpecRejects(): void
    {
        $guard = new RuleRevisionSpecGuard($this->createMock(IRules::class));
        try {
            $guard->assertEntries(1, [
                RuleCommitEntry::put('junk', new RuleVersionBody(
                    'item',
                    'Junk',
                    '',
                    [
                        'category' => 'armor',
                        'armor' => ['resistance_slots' => 'nope'],
                    ],
                    [],
                    [],
                    'needs_work',
                )),
            ], null);
            self::fail('broken item spec must reject');
        } catch (RuleSpaceSpecRejectedException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
            self::assertSame('junk', $exception->getErrorDetails()['problems'][0]['ruleCode']);
            self::assertSame(['spec.resistance_slots'], $exception->getErrorDetails()['problems'][0]['messages']);
        }
    }
}
