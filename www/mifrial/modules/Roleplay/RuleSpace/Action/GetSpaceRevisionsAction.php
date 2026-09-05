<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Лента ревизий мира.
 */
final class GetSpaceRevisionsAction implements IActionHandler
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
     * Возвращает SpaceRevisionMeta[].
     *
     * @param int $spaceId Мир.
     *
     * @return array<int, array<string, mixed>> Лента.
     */
    public function handle(int $spaceId): array
    {
        return $this->ruleSpaceHttpService->getRevisions($spaceId);
    }
}
