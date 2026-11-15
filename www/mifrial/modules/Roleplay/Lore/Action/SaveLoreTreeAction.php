<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\SaveLoreTreeInput;
use Mifrial\Roleplay\Lore\Service\LoreWorldHttpService;

/**
 * HTTP-обработчик замены дерева разделов.
 */
final class SaveLoreTreeAction implements IActionHandler
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
     * @param SaveLoreTreeInput $input JSON.
     *
     * @return array<string, mixed> Мир с новым деревом.
     */
    public function handle(SaveLoreTreeInput $input): array
    {
        return $this->service->saveTree($input);
    }
}
