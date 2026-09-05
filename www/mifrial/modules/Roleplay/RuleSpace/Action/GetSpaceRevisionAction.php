<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Dto\Action\GetRevisionInput;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Срез ревизии мира.
 */
final class GetSpaceRevisionAction implements IActionHandler
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
     * @param GetRevisionInput $input JSON.
     *
     * @return array<string, mixed> Срез.
     */
    public function handle(GetRevisionInput $input): array
    {
        return $this->ruleSpaceHttpService->getRevision($input);
    }
}
