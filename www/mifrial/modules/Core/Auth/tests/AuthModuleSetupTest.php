<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\Auth\Schema\AuthSchema;
use Mifrial\Core\Auth\Table\AuthGroupSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthPasswordResetTable;
use Mifrial\Core\Auth\Table\AuthSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use PHPUnit\Framework\TestCase;

final class AuthModuleSetupTest extends TestCase
{
    /**
     * Setup отдаёт те же class-string, что AuthSchema.
     *
     * @return void
     */
    public function testTableClassesMatchAuthSchema(): void
    {
        $expected = [
            UserIdentityTable::class,
            AuthSessionTable::class,
            AuthSecurityPolicyTable::class,
            AuthGroupSecurityPolicyTable::class,
            AuthPasswordResetTable::class,
        ];

        self::assertSame($expected, AuthSchema::getTableClasses());
    }
}
