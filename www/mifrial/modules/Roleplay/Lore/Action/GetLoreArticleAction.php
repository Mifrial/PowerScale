<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\GetLoreArticleInput;
use Mifrial\Roleplay\Lore\Service\LoreArticleHttpService;

/**
 * HTTP-обработчик статьи кодекса.
 */
final class GetLoreArticleAction implements IActionHandler
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
     * @param GetLoreArticleInput $input JSON.
     *
     * @return array<string, mixed> Статья со ссылками.
     */
    public function handle(GetLoreArticleInput $input): array
    {
        return $this->service->get($input);
    }
}
