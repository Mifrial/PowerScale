<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameMemberListInput;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Список участников игры.
 */
final class GetGameMemberListAction implements IActionHandler
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
     * Возвращает строки.
     *
     * @param GetGameMemberListInput $input JSON.
     *
     * @return list<array<string, mixed>> Участники.
     */
    public function handle(GetGameMemberListInput $input): array
    {
        return $this->gameHttp->getMemberList($input);
    }
}
