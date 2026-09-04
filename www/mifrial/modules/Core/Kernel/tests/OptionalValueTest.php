<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;
use PHPUnit\Framework\TestCase;

final class OptionalValueTest extends TestCase
{
    /**
     * Absent, present, fromJson; getValue на absent.
     *
     * @return void
     */
    public function testStringBoolArrayPresence(): void
    {
        $absentName = OptionalString::absent();
        self::assertFalse($absentName->isPresent());
        try {
            $absentName->getValue();
            self::fail('absent getValue');
        } catch (KernelException $exception) {
            self::assertSame('OPTIONAL_ABSENT', $exception->getErrorCode());
        }

        self::assertNull(OptionalString::fromJson(null)->getValue());
        self::assertSame('Ann', OptionalString::fromJson('Ann')->getValue());
        self::assertFalse(OptionalBool::fromJson(false)->getValue());
        self::assertSame([1], OptionalArray::fromJson([1])->getValue());
        try {
            OptionalBool::fromJson(null);
            self::fail('bool null');
        } catch (KernelException $exception) {
            self::assertSame('OPTIONAL_JSON', $exception->getErrorCode());
        }
    }
}
