<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Tests;

use Mifrial\Core\Agent\Schema\AgentSchema;
use Mifrial\Core\Agent\Setup\AgentModuleSetup;
use Mifrial\Core\Agent\Table\AgentTable;
use PHPUnit\Framework\TestCase;

final class AgentModuleSetupTest extends TestCase
{
    /**
     * Setup совпадает с AgentSchema.
     *
     * @return void
     */
    public function testTableClassesMatchAgentSchema(): void
    {
        $expected = [AgentTable::class];
        self::assertSame($expected, AgentSchema::getTableClasses());
        self::assertSame($expected, (new AgentModuleSetup())->getTableClasses());
        self::assertSame([], (new AgentModuleSetup())->getDataSteps());
    }
}
