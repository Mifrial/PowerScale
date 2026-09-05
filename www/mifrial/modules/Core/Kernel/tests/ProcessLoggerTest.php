<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Interface\Service\ILogger;
use Mifrial\Core\Kernel\Service\ErrorLogLogger;
use Mifrial\Core\Kernel\Service\ProcessLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessLoggerTest extends TestCase
{
    /**
     * debug не вызывает фабрику таблицы.
     *
     * @return void
     */
    public function testDebugDoesNotResolveTableLogger(): void
    {
        $resolved = false;
        $processLogger = new ProcessLogger(
            new ErrorLogLogger(),
            static function () use (&$resolved): ILogger {
                $resolved = true;
                throw new RuntimeException('should not resolve');
            },
        );
        $processLogger->debug('x', []);
        self::assertFalse($resolved);
    }

    /**
     * Сбой фабрики не бросает наружу.
     *
     * @return void
     */
    public function testFactoryFailureStaysSilent(): void
    {
        $processLogger = new ProcessLogger(
            new ErrorLogLogger(),
            static function (): ILogger {
                throw new RuntimeException('no table');
            },
        );
        $processLogger->error('x', []);
        self::assertTrue(true);
    }
}
