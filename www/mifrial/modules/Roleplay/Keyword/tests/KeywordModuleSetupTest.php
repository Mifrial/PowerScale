<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Tests;

use Mifrial\Roleplay\Keyword\Schema\KeywordSchema;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use PHPUnit\Framework\TestCase;

final class KeywordModuleSetupTest extends TestCase
{
    /**
     * Карты совпадают со schema.
     *
     * @return void
     */
    public function testTableClassesMatchKeywordSchema(): void
    {
        self::assertSame(
            [KeywordTable::class],
            KeywordSchema::getTableClasses(),
        );
    }
}
