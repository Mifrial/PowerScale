<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Exception\MifrialException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Interface\Service\ILogger;
use Throwable;

/**
 * Запись непойманного CLI в ILogger.
 */
final class CliFailureReporter
{
    /**
     * Пишет error и не бросает.
     *
     * @param ILogger $logger Логер процесса.
     * @param string $source Код CLI.
     * @param Throwable $throwable Причина.
     *
     * @return void
     */
    public function report(ILogger $logger, string $source, Throwable $throwable): void
    {
        $context = [
            'source' => $source,
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ];
        if ($throwable instanceof MifrialException) {
            $context['errorCode'] = $throwable->getErrorCode();
        }

        $logger->error('Unhandled CLI error', $context);
    }

    /**
     * Берёт ILogger с extra Kernel после boot.
     *
     * @param IApplication $application Процесс.
     * @param string $source Код CLI.
     * @param Throwable $throwable Причина.
     *
     * @return void
     */
    public function reportFromApplication(IApplication $application, string $source, Throwable $throwable): void
    {
        $this->report($this->loggerFromApplication($application), $source, $throwable);
    }

    /**
     * Extra Kernel или ErrorLogLogger; не бросает.
     *
     * @param IApplication $application Процесс.
     *
     * @return ILogger Логер.
     */
    private function loggerFromApplication(IApplication $application): ILogger
    {
        try {
            $locator = $application->getLocator();
            if (!$locator->has(IKernelContainer::class)) {
                return new ErrorLogLogger();
            }

            $logger = $locator->get(IKernelContainer::class)->get(ILogger::class);
        } catch (Throwable) {
            return new ErrorLogLogger();
        }

        return $logger instanceof ILogger ? $logger : new ErrorLogLogger();
    }
}
