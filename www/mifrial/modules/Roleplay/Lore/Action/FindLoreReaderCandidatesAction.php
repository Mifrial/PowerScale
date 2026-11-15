<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Lore\Dto\Action\FindLoreReaderCandidatesInput;
use Mifrial\Roleplay\Lore\Service\LoreArticleHttpService;

/**
 * HTTP-обработчик поиска читателей secret-статьи.
 */
final class FindLoreReaderCandidatesAction implements IActionHandler
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
     * @param FindLoreReaderCandidatesInput $input JSON.
     *
     * @return array<string, mixed> Кандидаты в читатели.
     */
    public function handle(FindLoreReaderCandidatesInput $input): array
    {
        return $this->service->findReaderCandidates($input);
    }
}
