<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\DeclareGameActionInput;
use Mifrial\Roleplay\Game\Service\GameActionHttp;

/**
 * Не-атакующее действие.
 */
final class DeclareGameActionAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameActionHttp $actionHttp Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameActionHttp $actionHttp)
    {
    }

    /**
     * Выполняет команду.
     *
     * @param DeclareGameActionInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(DeclareGameActionInput $input): array
    {
        return $this->actionHttp->declareAction($input);
    }
}
