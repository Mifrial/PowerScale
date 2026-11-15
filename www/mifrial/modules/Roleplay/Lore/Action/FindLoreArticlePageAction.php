<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\FindLoreArticlePageInput;
use Mifrial\Roleplay\Lore\Service\LoreArticleHttpService;

/**
 * HTTP-обработчик страницы статей кодекса.
 */
final class FindLoreArticlePageAction implements IActionHandler
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
     * @param FindLoreArticlePageInput $input JSON.
     *
     * @return array<string, mixed> Страница статей.
     */
    public function handle(FindLoreArticlePageInput $input): array
    {
        return $this->service->findPage($input);
    }
}
