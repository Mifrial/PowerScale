<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Service\UserInputNormalizer;
use PHPUnit\Framework\TestCase;

final class UserInputNormalizerTest extends TestCase
{
    /**
     * Отвергает пустой login, пустой name и неизвестный ключ.
     *
     * @return void
     */
    public function testNewUserRejectsInvalid(): void
    {
        $normalizer = new UserInputNormalizer();
        try {
            $normalizer->newUser(['login' => '  ', 'name' => 'Ann']);
            self::fail('empty login must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->newUser(['login' => 'a', 'name' => '  ']);
            self::fail('empty name must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->newUser(['login' => 'a', 'name' => 'Ann', 'nope' => 1]);
            self::fail('unknown key must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Trim и пустой email → null; пустой patch недопустим.
     *
     * @return void
     */
    public function testTrimAndEmptyPatch(): void
    {
        $normalizer = new UserInputNormalizer();
        $profileFields = $normalizer->newUser([
            'login' => ' bob ',
            'name' => ' Bob ',
            'email' => '  ',
        ])->fields();
        self::assertSame('bob', $profileFields['login']);
        self::assertSame('Bob', $profileFields['name']);
        self::assertNull($profileFields['email']);
        self::assertTrue($profileFields['active']);
        self::assertArrayNotHasKey('registered_at', $profileFields);

        try {
            $normalizer->patch([]);
            self::fail('empty patch must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }
}
