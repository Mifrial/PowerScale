<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Schema\RuleSchema;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;
use PHPUnit\Framework\TestCase;

final class RuleModuleSetupTest extends TestCase
{
    /**
     * Карты совпадают со schema.
     *
     * @return void
     */
    public function testTableClassesMatchRuleSchema(): void
    {
        self::assertSame(
            [
                RuleTable::class,
                RuleSpaceTable::class,
                RuleVersionTable::class,
                RuleRevisionTable::class,
                RuleRevisionItemTable::class,
            ],
            RuleSchema::getTableClasses(),
        );
    }
}
