<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Dto\Action\CommitDraftInput;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Публикация выбранных правил.
 */
final class CommitSpaceDraftAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param RuleSpaceHttpService $ruleSpaceHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly RuleSpaceHttpService $ruleSpaceHttpService,
    ) {
    }

    /**
     * Возвращает SpaceRevision.
     *
     * @param CommitDraftInput $input JSON.
     *
     * @return array<string, mixed> Срез.
     */
    public function handle(CommitDraftInput $input): array
    {
        return $this->ruleSpaceHttpService->commitDraft($input);
    }
}
