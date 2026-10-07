<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\StartGameBattleInput;
use Mifrial\Roleplay\Game\Service\GameBattleHttp;

/**
 * Старт боя.
 */
final class StartGameBattleAction implements IActionHandler
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
     * Открывает бой.
     *
     * @param StartGameBattleInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(StartGameBattleInput $input): array
    {
        return $this->battleHttp->start($input);
    }
}
