<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\Logger\Dto\Action\FindLogPageInput;
use Mifrial\Core\Logger\Service\LoggerHttpService;

/**
 * Страница технического журнала.
 */
final class FindLogPageAction implements IActionHandler
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
     * Возвращает items и total.
     *
     * @param FindLogPageInput $input Страница.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function handle(FindLogPageInput $input): array
    {
        return $this->loggerHttpService->findPage($input);
    }
}
