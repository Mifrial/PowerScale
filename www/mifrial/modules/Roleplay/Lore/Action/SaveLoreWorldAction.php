<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\SaveLoreWorldInput;
use Mifrial\Roleplay\Lore\Service\LoreWorldHttpService;

/**
 * HTTP-обработчик сохранения мира кодекса.
 */
final class SaveLoreWorldAction implements IActionHandler
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
     * @param SaveLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Сохранённый мир.
     */
    public function handle(SaveLoreWorldInput $input): array
    {
        return $this->service->save($input);
    }
}
