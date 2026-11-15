<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\ExportLoreWorldInput;
use Mifrial\Roleplay\Lore\Service\LoreTransferHttpService;

/**
 * HTTP-обработчик экспорта мира кодекса.
 */
final class ExportLoreWorldAction implements IActionHandler
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
     * @param ExportLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Документ `powerscale.lore`.
     */
    public function handle(ExportLoreWorldInput $input): array
    {
        return $this->service->export($input);
    }
}
