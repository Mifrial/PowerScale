<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Versioning\Space\Dto\RevisionSliceKey;
use PHPUnit\Framework\TestCase;

final class RuleUnitTest extends TestCase
{
    /**
     * Ключ кэша часов для identity rule.
     *
     * @return void
     */
    public function testSliceKeyUsesRuleTable(): void
    {
        self::assertSame('vs:rule:3:2', RevisionSliceKey::make('rule', 3, 2));
    }

    /**
     * Пустой code put.
     *
     * @return void
     */
    public function testEmptyPutCodeIsInvalid(): void
    {
        try {
            RuleCommitEntry::put('  ', $this->body());
            self::fail('empty code must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустое имя тела.
     *
     * @return void
     */
    public function testEmptyNameIsInvalid(): void
    {
        try {
            new RuleVersionBody('ability', '  ', '', [], [], null, [], 'needs_work');
            self::fail('empty name must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Тело без пустых полей.
     *
     * @return RuleVersionBody Снимок.
     */
    private function body(): RuleVersionBody
    {
        return new RuleVersionBody('ability', 'Human', '', [], [], null, [], 'needs_work');
    }
}
