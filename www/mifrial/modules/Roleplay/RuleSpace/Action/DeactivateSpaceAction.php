<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Выключение мира.
 */
final class DeactivateSpaceAction implements IActionHandler
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
     * Возвращает null.
     *
     * @param int $id Space id.
     *
     * @return null Успех.
     */
    public function handle(int $id): mixed
    {
        return $this->ruleSpaceHttpService->deactivate($id);
    }
}
