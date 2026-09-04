<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Setup\UserModuleSetup;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class UserModuleSetupTest extends TestCase
{
    /**
     * Setup User отдаёт те же class-string, что UserSchema.
     *
     * @return void
     */
    public function testTableClassesMatchUserSchema(): void
    {
        $expected = [
            UserTable::class,
            UserGroupTable::class,
            UserGroupMemberTable::class,
        ];

        self::assertSame($expected, UserSchema::getTableClasses());
        self::assertSame($expected, (new UserModuleSetup())->getTableClasses());
        self::assertSame([], (new UserModuleSetup())->getDataSteps());
    }
}
