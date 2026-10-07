<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\RemoveGameMemberInput;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Снятие участника.
 */
final class RemoveGameMemberAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameHttp $gameHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameHttp $gameHttp,
    ) {
    }

    /**
     * Снимает строку.
     *
     * @param RemoveGameMemberInput $input JSON.
     *
     * @return null Пусто.
     */
    public function handle(RemoveGameMemberInput $input): null
    {
        return $this->gameHttp->removeMember($input);
    }
}
