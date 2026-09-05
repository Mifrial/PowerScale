<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;

/**
 * Мир по коду.
 */
final class GetSpaceByCodeAction implements IActionHandler
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
     * @param string $code Ключ URL.
     *
     * @return array<string, mixed> Мир.
     */
    public function handle(string $code): array
    {
        return $this->ruleSpaceHttpService->getByCode($code);
    }
}
