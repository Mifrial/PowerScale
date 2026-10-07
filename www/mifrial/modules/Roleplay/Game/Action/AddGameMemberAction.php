<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\AddGameMemberInput;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Добавление участника.
 */
final class AddGameMemberAction implements IActionHandler
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
     * Пишет строку.
     *
     * @param AddGameMemberInput $input JSON.
     *
     * @return array<string, mixed> Участник.
     */
    public function handle(AddGameMemberInput $input): array
    {
        return $this->gameHttp->addMember($input);
    }
}
