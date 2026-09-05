<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Interface\Service\ILogger;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Kernel\Service\CliFailureReporter;
use PHPUnit\Framework\TestCase;

final class CliFailureReporterTest extends TestCase
{
    /**
     * Пишет error с source и кодом Mifrial.
     *
     * @return void
     */
    public function testReportWritesErrorContext(): void
    {
        $entries = [];
        $logger = new class ($entries) implements ILogger {
            /**
             * @param array<int, array{level: string, message: string, context: array<string, mixed>}> $entries Записи.
             */
            public function __construct(private array &$entries)
            {
            }

            public function error(string $message, array $context = []): void
            {
                $this->entries[] = ['level' => 'error', 'message' => $message, 'context' => $context];
            }

            public function warning(string $message, array $context = []): void
            {
            }

            public function info(string $message, array $context = []): void
            {
            }

            public function debug(string $message, array $context = []): void
            {
            }
        };

        (new CliFailureReporter())->report(
            $logger,
            'mifrial.setup',
            new KernelException('SETUP_INVALID', 'broken graph'),
        );

        self::assertCount(1, $entries);
        self::assertSame('error', $entries[0]['level']);
        self::assertSame('Unhandled CLI error', $entries[0]['message']);
        self::assertSame('mifrial.setup', $entries[0]['context']['source']);
        self::assertSame(KernelException::class, $entries[0]['context']['class']);
        self::assertSame('SETUP_INVALID', $entries[0]['context']['errorCode']);
        self::assertSame('broken graph', $entries[0]['context']['message']);
    }

    /**
     * get(ILogger) бросил — reporter не бросает.
     *
     * @return void
     */
    public function testReportFromApplicationDoesNotThrowWhenPortMissing(): void
    {
        $kernelContainer = $this->createStub(IKernelContainer::class);
        $kernelContainer->method('get')->willThrowException(new KernelException('UNKNOWN_PORT', 'x'));
        $locator = $this->createStub(IServiceLocator::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($kernelContainer);
        $application = $this->createStub(IApplication::class);
        $application->method('getLocator')->willReturn($locator);

        (new CliFailureReporter())->reportFromApplication(
            $application,
            'mifrial.setup',
            new KernelException('SETUP_INVALID', 'broken graph'),
        );

        self::assertTrue(true);
    }
}
