<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Мир по id.
 */
final class GetSpaceAction implements IActionHandler
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
     * Возвращает Space.
     *
     * @param int $id Space id.
     *
     * @return array<string, mixed> Мир.
     */
    public function handle(int $id): array
    {
        return $this->ruleSpaceHttpService->get($id);
    }
}
