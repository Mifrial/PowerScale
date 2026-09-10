<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Roleplay\Character\Schema\CharacterSchema;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;
use PHPUnit\Framework\TestCase;

final class CharacterModuleSetupTest extends TestCase
{
    /**
     * Карты совпадают со schema.
     *
     * @return void
     */
    public function testTableClassesMatchCharacterSchema(): void
    {
        self::assertSame(
            [CharacterTable::class, CharacterViewerTable::class],
            CharacterSchema::getTableClasses(),
        );
    }
}
