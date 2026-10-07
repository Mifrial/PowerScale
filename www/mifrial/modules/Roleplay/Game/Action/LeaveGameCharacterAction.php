<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\LeaveGameCharacterInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Выход персонажа из игры.
 */
final class LeaveGameCharacterAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCharacterHttp $gameCharacterHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCharacterHttp $gameCharacterHttp,
    ) {
    }

    /**
     * Ставит left.
     *
     * @param LeaveGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(LeaveGameCharacterInput $input): array
    {
        return $this->gameCharacterHttp->leave($input);
    }
}
