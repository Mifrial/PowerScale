<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Dto\Action\CreateSpaceInput;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Создание мира.
 */
final class CreateSpaceAction implements IActionHandler
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
     * @param CreateSpaceInput $input JSON.
     *
     * @return array<string, mixed> Мир.
     */
    public function handle(CreateSpaceInput $input): array
    {
        return $this->ruleSpaceHttpService->create($input);
    }
}
