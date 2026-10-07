<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ApplyGameEconomyInput;
use Mifrial\Roleplay\Game\Service\GameEconomyHttp;

/**
 * Проведение операции экономики.
 */
final class ApplyGameEconomyAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameEconomyHttp $economyHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyHttp $economyHttp,
    ) {
    }

    /**
     * Проводит части.
     *
     * @param ApplyGameEconomyInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(ApplyGameEconomyInput $input): array
    {
        return $this->economyHttp->apply($input);
    }
}
