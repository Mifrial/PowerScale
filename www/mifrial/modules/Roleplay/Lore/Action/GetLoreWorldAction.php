<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\GetLoreWorldInput;
use Mifrial\Roleplay\Lore\Service\LoreWorldHttpService;

/**
 * HTTP-обработчик мира кодекса с деревом.
 */
final class GetLoreWorldAction implements IActionHandler
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
     * @param GetLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Мир с деревом.
     */
    public function handle(GetLoreWorldInput $input): array
    {
        return $this->service->get($input);
    }
}
