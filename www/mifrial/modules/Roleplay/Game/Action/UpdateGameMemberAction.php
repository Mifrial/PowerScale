<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameMemberInput;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Смена роли участника.
 */
final class UpdateGameMemberAction implements IActionHandler
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
     * Пишет роль.
     *
     * @param UpdateGameMemberInput $input JSON.
     *
     * @return array<string, mixed> Участник.
     */
    public function handle(UpdateGameMemberInput $input): array
    {
        return $this->gameHttp->updateMember($input);
    }
}
