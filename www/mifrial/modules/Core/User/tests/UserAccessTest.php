<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Http\RequestContext;
use Mifrial\Core\User\Service\UserAccess;
use PHPUnit\Framework\TestCase;

final class UserAccessTest extends TestCase
{
    /**
     * Нет актора — AUTH_REQUIRED.
     *
     * @return void
     */
    public function testRequireActorMissing(): void
    {
        $userAccess = new UserAccess(new RequestContext());
        try {
            $userAccess->requireActor();
            self::fail('must require actor');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_REQUIRED', $exception->getErrorCode());
        }
    }

    /**
     * Ключ, self и bypass-членство.
     *
     * @return void
     */
    public function testKeysSelfAndBypassMembership(): void
    {
        $requestContext = new RequestContext();
        $requestContext->setActor(new RequestActor(7, ['user.view'], false));
        $userAccess = new UserAccess($requestContext);
        self::assertSame(7, $userAccess->requireKey('user.view')->getUserId());
        self::assertSame(7, $userAccess->requireSelfOrKey(7, 'user.edit')->getUserId());
        try {
            $userAccess->requireSelfOrKey(8, 'user.edit');
            self::fail('stranger without key must fail');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $userAccess->assertCanAssignBypassMembership(false);
        try {
            $userAccess->assertCanAssignBypassMembership(true);
            self::fail('bypass membership without hasBypass must fail');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor(1, [], true));
        $userAccess->assertCanAssignBypassMembership(true);
        $userAccess->requireKey('user.anything');
    }

    /**
     * update: edit, свой профиль, чужой без ключа; deactivate не себя.
     *
     * @return void
     */
    public function testAssertCanUpdateAndDeactivate(): void
    {
        $requestContext = new RequestContext();
        $requestContext->setActor(new RequestActor(7, [], false));
        $userAccess = new UserAccess($requestContext);
        $userAccess->assertCanUpdate(7, false, false);
        try {
            $userAccess->assertCanUpdate(7, true, false);
            self::fail('self groups');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor(7, ['user.edit'], false));
        $userAccess->assertCanUpdate(8, true, true);
        $requestContext->setActor(new RequestActor(7, ['user.deactivate'], false));
        $userAccess->assertCanDeactivate(8);
        try {
            $userAccess->assertCanDeactivate(7);
            self::fail('self deactivate');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }
    }
}
