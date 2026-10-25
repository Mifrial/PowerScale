<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\RunGameActionInput;
use Mifrial\Roleplay\Game\Service\GameRunActionHttp;

/**
 * Единый вход действий боя.
 */
final class RunGameActionAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameRunActionHttp $runAction Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameRunActionHttp $runAction)
    {
    }

    /**
     * Выполняет действие.
     *
     * @param RunGameActionInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(RunGameActionInput $input): array
    {
        return $this->runAction->run($input);
    }
}
