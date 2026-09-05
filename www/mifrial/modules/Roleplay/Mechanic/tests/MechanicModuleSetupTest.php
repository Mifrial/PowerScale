<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Mifrial\Roleplay\Mechanic\Schema\MechanicSchema;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;
use PHPUnit\Framework\TestCase;

final class MechanicModuleSetupTest extends TestCase
{
    /**
     * Карты совпадают со schema.
     *
     * @return void
     */
    public function testTableClassesMatchMechanicSchema(): void
    {
        self::assertSame(
            [MechanicTable::class],
            MechanicSchema::getTableClasses(),
        );
    }
}
