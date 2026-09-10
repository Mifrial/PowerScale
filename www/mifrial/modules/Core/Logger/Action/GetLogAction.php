<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\Logger\Service\LoggerHttpService;

/**
 * Запись журнала по id.
 */
final class GetLogAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param LoggerHttpService $loggerHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly LoggerHttpService $loggerHttpService,
    ) {
    }

    /**
     * Возвращает LogEntry.
     *
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> Запись.
     */
    public function handle(int $id): array
    {
        return $this->loggerHttpService->get($id);
    }
}
