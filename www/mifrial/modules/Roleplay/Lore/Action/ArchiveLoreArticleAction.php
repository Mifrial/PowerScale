<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\ArchiveLoreArticleInput;
use Mifrial\Roleplay\Lore\Service\LoreArticleHttpService;

/**
 * HTTP-обработчик архива статьи кодекса.
 */
final class ArchiveLoreArticleAction implements IActionHandler
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
     * @param ArchiveLoreArticleInput $input JSON.
     *
     * @return array<string, mixed> Статья после смены архива.
     */
    public function handle(ArchiveLoreArticleInput $input): array
    {
        return $this->service->archive($input);
    }
}
