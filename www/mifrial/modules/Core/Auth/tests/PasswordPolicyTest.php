<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\Auth\Dto\PasswordPolicy;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    /**
     * JSON camelCase как фронт.
     *
     * @return void
     */
    public function testDefaultsJson(): void
    {
        self::assertSame([
            'minLength' => 4,
            'requireMixedCase' => false,
            'requireDigit' => false,
            'requireSpecialChar' => false,
        ], PasswordPolicy::defaults()->toJson());
    }

    /**
     * Defaults режут короче 4.
     *
     * @return void
     */
    public function testDefaultsRejectShort(): void
    {
        $this->expectException(AuthPolicyException::class);
        PasswordPolicy::defaults()->assertPassword('abc');
    }

    /**
     * Defaults принимают 4 символа без флагов.
     *
     * @return void
     */
    public function testDefaultsAcceptPlain(): void
    {
        PasswordPolicy::defaults()->assertPassword('abcd');
        $this->addToAssertionCount(1);
    }

    /**
     * Флаги mixed/digit/special.
     *
     * @return void
     */
    public function testStrictFlags(): void
    {
        $strictPolicy = new PasswordPolicy(4, true, true, true);
        $this->expectException(AuthPolicyException::class);
        $strictPolicy->assertPassword('Abcd');
    }

    /**
     * Строгий набор проходит.
     *
     * @return void
     */
    public function testStrictAccepts(): void
    {
        $strictPolicy = new PasswordPolicy(4, true, true, true);
        $strictPolicy->assertPassword('Ab1!');
        $this->addToAssertionCount(1);
    }

    /**
     * Наибольшая: max длины и OR флагов.
     *
     * @return void
     */
    public function testStrictestMerges(): void
    {
        $merged = PasswordPolicy::strictest([
            new PasswordPolicy(4, true, false, false),
            new PasswordPolicy(10, false, true, false),
        ]);
        self::assertSame([
            'minLength' => 10,
            'requireMixedCase' => true,
            'requireDigit' => true,
            'requireSpecialChar' => false,
        ], $merged->toJson());
    }
}
