<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
    /**
     * Проверяет round-trip unix UTC.
     *
     * @return void
     */
    public function testFromUnixToUnix(): void
    {
        $moment = DateTime::fromUnix(0);
        self::assertSame(0, $moment->toUnix());
        self::assertSame(1700000000, DateTime::fromUnix(1700000000)->toUnix());
    }

    /**
     * Проверяет, что now() — текущий unix UTC.
     *
     * @return void
     */
    public function testNowIsCurrentUnix(): void
    {
        $beforeUnix = time();
        $moment = DateTime::now();
        $afterUnix = time();
        self::assertGreaterThanOrEqual($beforeUnix, $moment->toUnix());
        self::assertLessThanOrEqual($afterUnix, $moment->toUnix());
    }
}
