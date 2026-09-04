<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Service\GroupInputNormalizer;
use PHPUnit\Framework\TestCase;

final class GroupInputNormalizerTest extends TestCase
{
    /**
     * Trim имени, defaults, паттерн ключа.
     *
     * @return void
     */
    public function testNewGroupDefaultsAndPermissionPattern(): void
    {
        $normalizer = new GroupInputNormalizer();
        $fields = $normalizer->newGroup([
            'name' => ' Admins ',
            'permissions' => [' user.view ', 'user.edit'],
        ])->fields();
        self::assertSame('Admins', $fields['name']);
        self::assertTrue($fields['active']);
        self::assertFalse($fields['bypass']);
        self::assertFalse($fields['assign_on_register']);
        self::assertSame(['user.view', 'user.edit'], $fields['permissions']);
    }

    /**
     * Пустое имя, дубль ключа, без точки, пустой patch.
     *
     * @return void
     */
    public function testRejectsInvalid(): void
    {
        $normalizer = new GroupInputNormalizer();
        try {
            $normalizer->newGroup(['name' => '  ']);
            self::fail('empty name must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->newGroup(['name' => 'G', 'permissions' => ['user.view', 'user.view']]);
            self::fail('duplicate key must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->newGroup(['name' => 'G', 'permissions' => ['userview']]);
            self::fail('key without dot must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->patch([]);
            self::fail('empty patch must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }
}
