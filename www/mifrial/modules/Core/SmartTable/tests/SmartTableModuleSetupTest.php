<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Setup\SmartTableModuleSetup;
use Mifrial\Core\SmartTable\Table\MetaFieldDefinition;
use Mifrial\Core\SmartTable\Table\MetaTableDefinition;
use PHPUnit\Framework\TestCase;

final class SmartTableModuleSetupTest extends TestCase
{
    /**
     * CLI setup обязан отдать PHP-классы словаря.
     *
     * @return void
     */
    public function testTableClassesAreMetaDefinitions(): void
    {
        self::assertSame(
            [MetaTableDefinition::class, MetaFieldDefinition::class],
            (new SmartTableModuleSetup())->getTableClasses(),
        );
        self::assertSame([], (new SmartTableModuleSetup())->getDataSteps());
    }
}
