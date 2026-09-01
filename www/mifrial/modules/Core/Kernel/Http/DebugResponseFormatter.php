<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use Mifrial\Core\Kernel\Dto\ActionResponse;
use Throwable;

/**
 * Маскирование инфраструктурных ошибок по флагу debug.
 */
final class DebugResponseFormatter
{
    /**
     * Создаёт форматтер с режимом отладки.
     *
     * @param bool $debug Признак подробных инфраструктурных ошибок.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $debug,
    ) {
    }

    /**
     * Собирает INTERNAL из непойманного исключения.
     *
     * @param Throwable $throwable Непойманная ошибка.
     *
     * @return ActionResponse Конверт INTERNAL.
     */
    public function internalError(Throwable $throwable): ActionResponse
    {
        if (!$this->debug) {
            return ActionResponse::fail('INTERNAL', 'Internal error');
        }

        return ActionResponse::fail(
            'INTERNAL',
            $throwable::class . ': ' . $throwable->getMessage(),
            ['trace' => $throwable->getTraceAsString()],
        );
    }

    /**
     * Скрывает FQCN в инфраструктурных ошибках вне debug.
     *
     * @param ActionResponse $response Исходный конверт.
     *
     * @return ActionResponse Конверт для клиента.
     */
    public function maskInfrastructure(ActionResponse $response): ActionResponse
    {
        if ($this->debug || $response->success || $response->error === null) {
            return $response;
        }

        $errorCode = $response->error['code'] ?? '';
        if ($errorCode !== 'INVALID_HANDLER') {
            return $response;
        }

        return ActionResponse::fail('INVALID_HANDLER', 'Invalid action handler');
    }
}
