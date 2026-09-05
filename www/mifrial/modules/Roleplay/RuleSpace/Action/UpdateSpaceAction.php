<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Dto\Action\UpdateSpaceInput;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Обновление мета мира.
 */
final class UpdateSpaceAction implements IActionHandler
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
     * @param UpdateSpaceInput $input JSON.
     *
     * @return array<string, mixed> Мир.
     */
    public function handle(UpdateSpaceInput $input): array
    {
        return $this->ruleSpaceHttpService->update($input);
    }
}
