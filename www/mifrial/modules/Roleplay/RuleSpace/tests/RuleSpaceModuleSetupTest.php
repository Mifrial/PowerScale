<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Roleplay\RuleSpace\Schema\RuleSpaceSchema;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;
use PHPUnit\Framework\TestCase;

final class RuleSpaceModuleSetupTest extends TestCase
{
    /**
     * Карты совпадают со schema.
     *
     * @return void
     */
    public function testTableClassesMatchSchema(): void
    {
        self::assertSame(
            [
                RuleSpaceMetaTable::class,
                RuleSpaceCatalogSectionTable::class,
                RuleSpaceCatalogItemTable::class,
                RuleSpaceRevisionCatalogTable::class,
            ],
            RuleSpaceSchema::getTableClasses(),
        );
    }
}
