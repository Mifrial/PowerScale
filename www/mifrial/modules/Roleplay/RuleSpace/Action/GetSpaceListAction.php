<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Список живых миров.
 */
final class GetSpaceListAction implements IActionHandler
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
     * Возвращает Space[].
     *
     * @return array<int, array<string, mixed>> Миры.
     */
    public function handle(): array
    {
        return $this->ruleSpaceHttpService->getList();
    }
}
