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
