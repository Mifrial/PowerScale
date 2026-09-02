<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use PHPUnit\Framework\TestCase;

final class AuthSettingsTest extends TestCase
{
    /**
     * Пустой срез даёт cookie_secure false.
     *
     * @return void
     */
    public function testEmptySectionDefaults(): void
    {
        $settings = AuthSettings::fromSection(null);

        self::assertFalse($settings->cookieSecure());
        self::assertSame('', $settings->operatorLogin());
    }

    /**
     * Не-массив — SETUP_INVALID.
     *
     * @return void
     */
    public function testRejectsNonArray(): void
    {
        $this->expectException(SetupException::class);
        AuthSettings::fromSection('nope');
    }

    /**
     * Неполный оператор для seed.
     *
     * @return void
     */
    public function testAssertOperatorComplete(): void
    {
        $settings = AuthSettings::fromSection(['operator_login' => 'admin']);
        $this->expectException(SetupException::class);
        $settings->assertOperatorComplete();
    }
}
