<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\SaveLoreArticleInput;
use Mifrial\Roleplay\Lore\Service\LoreArticleHttpService;

/**
 * HTTP-обработчик сохранения статьи кодекса.
 */
final class SaveLoreArticleAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param LoreArticleHttpService $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreArticleHttpService $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param SaveLoreArticleInput $input JSON.
     *
     * @return array<string, mixed> Сохранённая статья.
     */
    public function handle(SaveLoreArticleInput $input): array
    {
        return $this->service->save($input);
    }
}
