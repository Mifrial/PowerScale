<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\ImportLoreWorldInput;
use Mifrial\Roleplay\Lore\Service\LoreTransferHttpService;

/**
 * HTTP-обработчик импорта мира кодекса.
 */
final class ImportLoreWorldAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param LoreTransferHttpService $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreTransferHttpService $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param ImportLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Отчёт импорта.
     */
    public function handle(ImportLoreWorldInput $input): array
    {
        return $this->service->import($input);
    }
}
