<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use LogicException;
use Mifrial\Core\Kernel\Http\ThrowableTraceFormatter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Цепочка previous в строке стека.
 */
final class ThrowableTraceFormatterTest extends TestCase
{
    /**
     * Верхний кадр и previous попадают в одну строку.
     *
     * @return void
     */
    public function testFormatIncludesPrevious(): void
    {
        $trace = (new ThrowableTraceFormatter())->format(
            new RuntimeException('boom', 0, new LogicException('inner')),
        );

        self::assertStringContainsString('RuntimeException: boom', $trace);
        self::assertStringContainsString('LogicException: inner', $trace);
    }
}
