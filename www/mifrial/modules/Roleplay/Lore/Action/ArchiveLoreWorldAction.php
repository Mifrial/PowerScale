<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\ArchiveLoreWorldInput;
use Mifrial\Roleplay\Lore\Service\LoreWorldHttpService;

/**
 * HTTP-обработчик архива мира кодекса.
 */
final class ArchiveLoreWorldAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param LoreWorldHttpService $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreWorldHttpService $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param ArchiveLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Мир после смены архива.
     */
    public function handle(ArchiveLoreWorldInput $input): array
    {
        return $this->service->archive($input);
    }
}
