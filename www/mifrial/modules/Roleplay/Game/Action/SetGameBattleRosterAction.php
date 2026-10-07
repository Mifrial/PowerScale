<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\SetGameBattleRosterInput;
use Mifrial\Roleplay\Game\Service\GameBattleHttp;

/**
 * Смена состава боя.
 */
final class SetGameBattleRosterAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameBattleHttp $battleHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleHttp $battleHttp,
    ) {
    }

    /**
     * Меняет состав.
     *
     * @param SetGameBattleRosterInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(SetGameBattleRosterInput $input): array
    {
        return $this->battleHttp->setRoster($input);
    }
}
