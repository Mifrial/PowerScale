<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Service\ILogger;

/**
 * Журнал и JSON-конверт доменного ActionException.
 */
final class ActionExceptionReporter
{
    /**
     * Создаёт репортёр с журналом и режимом debug.
     *
     * @param ILogger|null $logger Журнал; null — только конверт.
     * @param bool $debug Класть trace в JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly ?ILogger $logger,
        private readonly bool $debug,
    ) {
    }

    /**
     * Пишет стек в журнал и собирает fail.
     *
     * @param ActionException $actionException Доменный отказ.
     * @param string|null $source Код действия.
     *
     * @return ActionResponse Конверт для клиента.
     */
    public function report(ActionException $actionException, ?string $source): ActionResponse
    {
        $this->writeLog($actionException, $source);

        return $this->failResponse($actionException);
    }

    /**
     * Конверт fail; trace только при debug.
     *
     * @param ActionException $actionException Доменный отказ.
     *
     * @return ActionResponse Конверт.
     */
    private function failResponse(ActionException $actionException): ActionResponse
    {
        $details = $actionException->getErrorDetails();
        if ($this->debug) {
            $details['trace'] = (new ThrowableTraceFormatter())->format($actionException);
        }

        return ActionResponse::fail(
            $actionException->getErrorCode(),
            $actionException->getMessage(),
            $details,
        );
    }

    /**
     * Пишет отказ в журнал, если логер передан.
     *
     * @param ActionException $actionException Доменный отказ.
     * @param string|null $source Код действия.
     *
     * @return void
     */
    private function writeLog(ActionException $actionException, ?string $source): void
    {
        if (!$this->logger instanceof ILogger) {
            return;
        }

        $context = [
            'class' => $actionException::class,
            'message' => $actionException->getMessage(),
            'file' => $actionException->getFile(),
            'line' => $actionException->getLine(),
            'trace' => (new ThrowableTraceFormatter())->format($actionException),
            'errorCode' => $actionException->getErrorCode(),
        ];
        if (is_string($source) && $source !== '') {
            $context['source'] = $source;
        }

        $this->logger->error('Action failed', $context);
    }
}
