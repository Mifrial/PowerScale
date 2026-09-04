<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Tests;

use Mifrial\Core\Mail\Schema\MailSchema;
use Mifrial\Core\Mail\Table\MailEventTable;
use Mifrial\Core\Mail\Table\MailJobTable;
use Mifrial\Core\Mail\Table\MailTemplateTable;
use PHPUnit\Framework\TestCase;

final class MailModuleSetupTest extends TestCase
{
    /**
     * Карты совпадают со schema.
     *
     * @return void
     */
    public function testTableClassesMatchMailSchema(): void
    {
        self::assertSame(
            [MailEventTable::class, MailTemplateTable::class, MailJobTable::class],
            MailSchema::getTableClasses(),
        );
    }
}
