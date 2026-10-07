<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\EndGameBattleInput;
use Mifrial\Roleplay\Game\Service\GameBattleHttp;

/**
 * Конец одного боя.
 */
final class EndGameBattleAction implements IActionHandler
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
     * Закрывает бой.
     *
     * @param EndGameBattleInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(EndGameBattleInput $input): array
    {
        return $this->battleHttp->end($input);
    }
}
