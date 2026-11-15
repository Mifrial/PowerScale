<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\FindLoreWorldPageInput;
use Mifrial\Roleplay\Lore\Service\LoreWorldHttpService;

/**
 * HTTP-обработчик страницы миров кодекса.
 */
final class FindLoreWorldPageAction implements IActionHandler
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
     * @param FindLoreWorldPageInput $input JSON.
     *
     * @return array<string, mixed> Страница миров.
     */
    public function handle(FindLoreWorldPageInput $input): array
    {
        return $this->service->findPage($input);
    }
}
