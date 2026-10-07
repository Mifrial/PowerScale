<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\RollGameInitiativeInput;
use Mifrial\Roleplay\Game\Service\GameBattleInitiativeHttp;

/**
 * Порядок хода одного боя.
 */
final class RollGameInitiativeAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameBattleInitiativeHttp $initiativeHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleInitiativeHttp $initiativeHttp,
    ) {
    }

    /**
     * Считает порядок.
     *
     * @param RollGameInitiativeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(RollGameInitiativeInput $input): array
    {
        return $this->initiativeHttp->roll($input);
    }
}
